<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function client_area_db(): PDO
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!client_area_is_configured()) {
        throw new RuntimeException('Client area is not configured yet.');
    }

    $database = client_area_config()['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $database['host'],
        (int) $database['port'],
        $database['name'],
        $database['charset']
    );

    $pdo = new PDO($dsn, $database['user'], $database['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
