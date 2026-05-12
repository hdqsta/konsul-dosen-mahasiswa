<?php
require_once 'includes/db.php';
$pdo = get_pdo();

try {
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('mahasiswa','dosen','admin') NOT NULL");
    $pdo->exec("INSERT IGNORE INTO users (identifier, nama, password, role, is_active) VALUES ('admin', 'Administrator', '$2y$12$ov6248.6jCThig2ykmEf/OWAOcCaI/gPVHRKI02XR7okGm9fxxNr.', 'admin', 1)");
    echo "Database updated successfully.\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
