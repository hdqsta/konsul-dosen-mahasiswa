<?php
// Batalkan booking pending
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('mahasiswa');
$pdo = get_pdo();

// CSRF
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    error_log("[CSRF] mahasiswa_id={$user['user_id']} — batal booking.");
    exit('403 Forbidden.');
}

$booking_id = (int)($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['flash_error'] = 'Booking tidak valid.';
    header('Location: /mahasiswa/dashboard.php');
    exit();
}

// Validasi kepemilikan
$stmt = $pdo->prepare("
    SELECT b.id, b.status, b.jadwal_id, j.dosen_id
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    WHERE b.id = :booking_id
      AND b.mahasiswa_id = :mahasiswa_id
      AND b.status = 'pending'
");
$stmt->execute([
    'booking_id'    => $booking_id,
    'mahasiswa_id' => $user['user_id'],
]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Booking tidak ditemukan atau sudah diproses.';
    header('Location: /mahasiswa/dashboard.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // Update status booking ke 'dibatalkan'
    $pdo->prepare("UPDATE booking SET status = 'dibatalkan' WHERE id = ?")
        ->execute([$booking_id]);

    // Kembalikan jadwal ke 'tersedia'
    $pdo->prepare("UPDATE jadwal SET status = 'tersedia' WHERE id = ?")
        ->execute([$booking['jadwal_id']]);

    $pdo->commit();

    // Notifikasi ke dosen
    add_notifikasi(
        (int)$booking['dosen_id'],
        "Mahasiswa membatalkan booking #{$booking_id}"
    );

    $_SESSION['flash'] = 'Booking berhasil dibatalkan.';

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("[BATAL ERROR] " . $e->getMessage());
    $_SESSION['flash_error'] = 'Terjadi kesalahan sistem.';

} finally {
    header('Location: /mahasiswa/dashboard.php');
    exit();
}