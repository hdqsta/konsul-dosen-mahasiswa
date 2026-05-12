<?php
// Hapus slot jadwal tersedia
require_once '../includes/db.php';
require_once '../includes/auth.php';

$user = require_role('dosen');
$pdo = get_pdo();

// CSRF
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('403 Forbidden.');
}

$jadwal_id = (int)($_POST['jadwal_id'] ?? 0);

if ($jadwal_id <= 0) {
    $_SESSION['flash_error'] = 'Slot tidak valid.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// Validasi kepemilikan + status tersedia
$stmt = $pdo->prepare("
    SELECT id FROM jadwal
    WHERE id = :id
      AND dosen_id = :dosen_id
      AND status = 'tersedia'
");
$stmt->execute([
    'id'       => $jadwal_id,
    'dosen_id' => $user['user_id'],
]);
$jadwal = $stmt->fetch();

if (!$jadwal) {
    $_SESSION['flash_error'] = 'Slot tidak ditemukan atau sudah di-booking.';
    header('Location: /dosen/dashboard.php');
    exit();
}

try {
    $pdo->prepare("DELETE FROM jadwal WHERE id = ?")->execute([$jadwal_id]);
    $_SESSION['flash'] = 'Slot berhasil dihapus.';

} catch (PDOException $e) {
    error_log("[HAPUS SLOT ERROR] " . $e->getMessage());
    $_SESSION['flash_error'] = 'Terjadi kesalahan sistem.';

} finally {
    header('Location: /dosen/dashboard.php');
    exit();
}