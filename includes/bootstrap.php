<?php

declare(strict_types=1);

const CLIENT_AREA_ROOT = __DIR__ . '/../';
const CLIENT_AREA_CONFIG_FILE = CLIENT_AREA_ROOT . 'config/client-area.php';

function client_area_defaults(): array
{
    return [
        'base_url' => '',
        'database' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'name' => '',
            'user' => '',
            'password' => '',
            'charset' => 'utf8mb4',
        ],
        'mail' => [
            'from_email' => '',
            'from_name' => 'Rohan Latimer',
            'link_ttl_minutes' => 30,
        ],
        'security' => [
            'session_name' => 'rocodes_client',
            'request_cooldown_seconds' => 30,
        ],
    ];
}

function client_area_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $config = client_area_defaults();

    if (is_file(CLIENT_AREA_CONFIG_FILE)) {
        $loaded = require CLIENT_AREA_CONFIG_FILE;

        if (is_array($loaded)) {
            $config = array_replace_recursive($config, $loaded);
        }
    }

    return $config;
}

function client_area_is_configured(): bool
{
    $config = client_area_config();

    return is_file(CLIENT_AREA_CONFIG_FILE)
        && $config['database']['name'] !== ''
        && $config['database']['user'] !== ''
        && $config['mail']['from_email'] !== '';
}

function client_area_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = client_area_config();

    session_name($config['security']['session_name']);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    session_start();
}

client_area_start_session();

function client_area_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function client_area_random_bytes(int $length): string
{
    if (function_exists('random_bytes')) {
        return call_user_func('random_bytes', $length);
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes($length, $strong);

        if ($bytes !== false && $strong === true) {
            return $bytes;
        }
    }

    throw new RuntimeException('No secure random byte generator is available.');
}

function client_area_base_url(): string
{
    $configured = trim((string) client_area_config()['base_url']);

    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function client_area_url(string $path = '/account/'): string
{
    return client_area_base_url() . '/' . ltrim($path, '/');
}

function client_area_set_flash(string $type, string $message): void
{
    $_SESSION['client_area_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function client_area_get_flash(): ?array
{
    if (!isset($_SESSION['client_area_flash'])) {
        return null;
    }

    $flash = $_SESSION['client_area_flash'];
    unset($_SESSION['client_area_flash']);

    return is_array($flash) ? $flash : null;
}

function client_area_redirect(string $path = '/account/'): never
{
    header('Location: ' . client_area_url($path));
    exit;
}

function client_area_csrf_token(): string
{
    if (!isset($_SESSION['client_area_csrf'])) {
        $_SESSION['client_area_csrf'] = bin2hex(client_area_random_bytes(32));
    }

    return $_SESSION['client_area_csrf'];
}

function client_area_verify_csrf(?string $token): bool
{
    if (!is_string($token) || !isset($_SESSION['client_area_csrf'])) {
        return false;
    }

    return hash_equals($_SESSION['client_area_csrf'], $token);
}

function client_area_rate_limit_key(): string
{
    return 'client_area_rate_limit_' . sha1((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function client_area_is_rate_limited(): bool
{
    $key = client_area_rate_limit_key();
    $lastRequest = $_SESSION[$key] ?? null;

    if (!is_int($lastRequest)) {
        return false;
    }

    $cooldown = (int) client_area_config()['security']['request_cooldown_seconds'];

    return (time() - $lastRequest) < $cooldown;
}

function client_area_touch_rate_limit(): void
{
    $_SESSION[client_area_rate_limit_key()] = time();
}

function client_area_client_id(): ?int
{
    $clientId = $_SESSION['client_area_client_id'] ?? null;

    return is_int($clientId) ? $clientId : null;
}

function client_area_login(int $clientId): void
{
    session_regenerate_id(true);
    $_SESSION['client_area_client_id'] = $clientId;
}

function client_area_logout(): void
{
    unset($_SESSION['client_area_client_id']);
    session_regenerate_id(true);
}
