<?php
require_once 'includes/db.php';
$pdo = get_pdo();

try {
    $hash = '$2y$12$ov6248.6jCThig2ykmEf/OWAOcCaI/gPVHRKI02XR7okGm9fxxNr.';
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE identifier = 'admin'");
    $stmt->execute([$hash]);
    echo "Admin password fixed.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
