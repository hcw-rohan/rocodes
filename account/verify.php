<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (!client_area_is_configured()) {
    client_area_set_flash('error', 'Client area setup is incomplete.');
    client_area_redirect('/account/');
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    client_area_set_flash('error', 'That sign-in link is invalid.');
    client_area_redirect('/account/');
}

try {
    $client = client_area_consume_magic_link(client_area_db(), $token);

    if ($client === null) {
        client_area_set_flash('error', 'That sign-in link is invalid or has expired.');
        client_area_redirect('/account/');
    }

    client_area_login((int) $client['id']);
    client_area_set_flash('success', 'You are now signed in.');
} catch (Throwable $throwable) {
    client_area_set_flash('error', 'We could not sign you in right now.');
}

client_area_redirect('/account/');
