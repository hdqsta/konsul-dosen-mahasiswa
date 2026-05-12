<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$user = require_role('mahasiswa');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /mahasiswa/riwayat.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Token keamanan tidak valid.';
    header('Location: /mahasiswa/riwayat.php');
    exit;
}

$booking_id = $_POST['booking_id'] ?? null;
$rating     = $_POST['rating'] ?? null;

if (!$booking_id || !$rating || $rating < 1 || $rating > 5) {
    $_SESSION['flash_error'] = 'Rating tidak valid.';
    header('Location: /mahasiswa/riwayat.php');
    exit;
}

$pdo = get_pdo();

// Verifikasi booking milik mahasiswa ini dan status selesai
$stmt = $pdo->prepare("SELECT status FROM booking WHERE id = ? AND mahasiswa_id = ?");
$stmt->execute([$booking_id, $user['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['flash_error'] = 'Data konsultasi tidak ditemukan.';
} elseif ($booking['status'] !== 'selesai') {
    $_SESSION['flash_error'] = 'Hanya konsultasi yang sudah selesai yang bisa diberi rating.';
} else {
    // Update rating
    $stmt = $pdo->prepare("UPDATE booking SET rating = ? WHERE id = ?");
    if ($stmt->execute([$rating, $booking_id])) {
        $_SESSION['flash'] = 'Terima kasih! Rating berhasil dikirim.';
    } else {
        $_SESSION['flash_error'] = 'Gagal menyimpan rating.';
    }
}

header('Location: /mahasiswa/riwayat.php');
exit;
