<?php
// Database connection singleton
$pdo = null;

function get_pdo(): PDO {
    global $pdo;

    if ($pdo === null) {
        $host     = 'localhost';
        $dbname   = 'konsultasi_db';
        $username = 'root';
        $password = '';
        $charset  = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, $username, $password, $options);

        // Set timezone explicitly untuk sinkronisasi temporal PHP-DB
        $pdo->exec("SET time_zone = '+07:00'");

        // Auto-cleanup jadwal kadaluarsa
        require_once __DIR__ . '/auto_cleanup.php';
        auto_cleanup_jadwal_kadaluarsa();
    }

    return $pdo;
}

// Alias compatibility
function get_db(): PDO {
    return get_pdo();
}