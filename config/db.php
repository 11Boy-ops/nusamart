<?php
declare(strict_types=1);

/**
 * Koneksi database menggunakan PDO.
 * Default dibuat sesuai konfigurasi XAMPP: user root tanpa password.
 * Sesuaikan nilai di bawah jika konfigurasi MySQL Anda berbeda.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $database = getenv('DB_NAME') ?: 'nusamart_db';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';

    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
    return $pdo;
}
