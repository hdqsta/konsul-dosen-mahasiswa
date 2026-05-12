<?php
// Tandai booking selesai
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('dosen');
$pdo = get_pdo();

// CSRF
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('403 Forbidden.');
}

$booking_id = (int)($_POST['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['flash_error'] = 'Booking tidak valid.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// Validasi kepemilikan + status approved
$stmt = $pdo->prepare("
    SELECT b.id, b.status, b.mahasiswa_id, b.jadwal_id, j.dosen_id
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    WHERE b.id = :booking_id
      AND b.status = 'approved'
");
$stmt->execute(['booking_id' => $booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Booking tidak ditemukan atau bukan status approved.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// Cek kepemilikan
if ((int)$booking['dosen_id'] !== (int)$user['user_id']) {
    http_response_code(403);
    exit('403 Forbidden: Akses ditolak.');
}

try {
    $pdo->beginTransaction();

    // Catatan dari dosen (opsional)
    $catatan_dosen = trim($_POST['catatan_dosen'] ?? '');

    $pdo->prepare("
        UPDATE booking
        SET status = 'selesai',
            catatan_dosen = ?
        WHERE id = ?
    ")->execute([
        $catatan_dosen ? htmlspecialchars($catatan_dosen, ENT_QUOTES, 'UTF-8') : null,
        $booking_id,
    ]);

    // Kembalikan jadwal ke 'tersedia'
    $pdo->prepare("UPDATE jadwal SET status = 'tersedia' WHERE id = ?")
        ->execute([$booking['jadwal_id']]);

    $pdo->commit();

    // Notifikasi ke mahasiswa
    add_notifikasi(
        (int)$booking['mahasiswa_id'],
        "Konsultasi #{$booking_id} ditandai selesai oleh dosen."
    );

    $_SESSION['flash'] = 'Booking ditandai selesai.';

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("[SELESAI ERROR] " . $e->getMessage());
    $_SESSION['flash_error'] = 'Terjadi kesalahan sistem.';

} finally {
    header('Location: /dosen/dashboard.php');
    exit();
}