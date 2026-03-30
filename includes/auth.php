<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function client_area_find_client_by_email(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, email, name,
                hosting_location, hosting_storage_gb, hosting_cpu_cores, hosting_memory_gb,
                hosting_payment_cycle, hosting_cost, hosting_last_payment_date, hosting_custom_text,
                maintenance_type, maintenance_cost, maintenance_last_payment_date, maintenance_billing_frequency
         FROM clients
         WHERE email = :email AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['email' => strtolower(trim($email))]);
    $client = $statement->fetch();

    return is_array($client) ? $client : null;
}

function client_area_find_client_by_id(PDO $pdo, int $clientId): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, email, name,
                hosting_location, hosting_storage_gb, hosting_cpu_cores, hosting_memory_gb,
                hosting_payment_cycle, hosting_cost, hosting_last_payment_date, hosting_custom_text,
                maintenance_type, maintenance_cost, maintenance_last_payment_date, maintenance_billing_frequency
         FROM clients
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['id' => $clientId]);
    $client = $statement->fetch();

    return is_array($client) ? $client : null;
}

function client_area_issue_magic_link(PDO $pdo, array $client): string
{
    $selector = bin2hex(client_area_random_bytes(8));
    $verifier = bin2hex(client_area_random_bytes(32));
    $tokenHash = hash('sha256', $verifier);
    $expiresAt = (new DateTimeImmutable())
        ->modify('+' . (int) client_area_config()['mail']['link_ttl_minutes'] . ' minutes')
        ->format('Y-m-d H:i:s');

    $statement = $pdo->prepare(
        'INSERT INTO magic_login_tokens
            (client_id, email, selector, token_hash, expires_at, requested_ip, requested_user_agent)
         VALUES
            (:client_id, :email, :selector, :token_hash, :expires_at, :requested_ip, :requested_user_agent)'
    );
    $statement->execute([
        'client_id' => $client['id'],
        'email' => $client['email'],
        'selector' => $selector,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'requested_ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        'requested_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    return $selector . '.' . $verifier;
}

function client_area_send_magic_link(array $client, string $token): bool
{
    $config = client_area_config();
    $verifyUrl = client_area_url('/account/verify.php?token=' . rawurlencode($token));
    $ttl = (int) $config['mail']['link_ttl_minutes'];
    $subject = 'Your account link';
    $displayName = trim((string) ($client['name'] ?? ''));
    $greetingName = $displayName !== '' ? $displayName : $client['email'];
    $message = implode("\r\n", [
        'He ' . $greetingName . ',',
        '',
        'Use the link below to sign in to your account:',
        $verifyUrl,
        '',
        'This link expires in ' . $ttl . ' minutes and can only be used once.',
        '',
        'If you did not request this email, you can safely ignore it.',
        '',
        'Cheers, Ro.'
    ]);
    $headers = [
        'From: ' . $config['mail']['from_name'] . ' <' . $config['mail']['from_email'] . '>',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    return mail($client['email'], $subject, $message, implode("\r\n", $headers));
}

function client_area_consume_magic_link(PDO $pdo, string $token): ?array
{
    $parts = explode('.', $token, 2);

    if (count($parts) !== 2) {
        return null;
    }

    [$selector, $verifier] = $parts;

    if (!ctype_xdigit($selector) || !ctype_xdigit($verifier)) {
        return null;
    }

    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare(
            'SELECT id, client_id, token_hash, expires_at, used_at
             FROM magic_login_tokens
             WHERE selector = :selector
             LIMIT 1
             FOR UPDATE'
        );
        $statement->execute(['selector' => $selector]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            $pdo->rollBack();

            return null;
        }

        $isExpired = strtotime((string) $row['expires_at']) < time();
        $isUsed = $row['used_at'] !== null;
        $isValid = hash_equals((string) $row['token_hash'], hash('sha256', $verifier));

        if ($isExpired || $isUsed || !$isValid) {
            $pdo->rollBack();

            return null;
        }

        $update = $pdo->prepare(
            'UPDATE magic_login_tokens
             SET used_at = NOW()
             WHERE id = :id'
        );
        $update->execute(['id' => $row['id']]);

        $client = client_area_find_client_by_id($pdo, (int) $row['client_id']);

        if ($client === null) {
            $pdo->rollBack();

            return null;
        }

        $pdo->commit();

        return $client;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $throwable;
    }
}

function client_area_cleanup_expired_tokens(PDO $pdo): void
{
    $statement = $pdo->prepare(
        'DELETE FROM magic_login_tokens
         WHERE expires_at < NOW() OR used_at < (NOW() - INTERVAL 30 DAY)'
    );
    $statement->execute();
}
