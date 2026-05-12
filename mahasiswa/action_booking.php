<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('mahasiswa');
$pdo = get_pdo();

// -----------------------------------------------------------
// VALIDASI CSRF
// -----------------------------------------------------------
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    error_log("[CSRF] mahasiswa_id={$user['user_id']} — token tidak cocok.");
    exit('403 Forbidden: Token CSRF tidak valid.');
}

$jadwal_id = (int)($_POST['jadwal_id'] ?? 0);
$topik     = trim($_POST['topik'] ?? '');

if ($jadwal_id <= 0 || empty($topik)) {
    $_SESSION['flash_error'] = 'Data tidak lengkap.';
    header('Location: /mahasiswa/dashboard.php');
    exit();
}

// -----------------------------------------------------------
// TRANSAKSI + FOR UPDATE (PATCH 1 — Race Condition)
// -----------------------------------------------------------
try {
    $pdo->beginTransaction();

    // Perbaikan: validasi temporal — hanya jadwal hari ini/ke depan yang bisa dibooking
    $stmt = $pdo->prepare("
        SELECT status FROM jadwal
        WHERE id = ?
          AND (tanggal > CURDATE() OR (tanggal = CURDATE() AND jam_mulai > CURTIME()))
        FOR UPDATE
    ");
    $stmt->execute([$jadwal_id]);
    $jadwal = $stmt->fetch();

    if (!$jadwal) {
        error_log("[BOOKING] jadwal_id=$jadwal_id tidak ditemukan atau sudah kedaluwarsa.");
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Slot tidak ditemukan atau sudah kedaluwarsa.';
        header('Location: /mahasiswa/dashboard.php');
        exit();
    }

    if ($jadwal['status'] !== 'tersedia') {
        error_log("[RACE] mahasiswa_id={$user['user_id']} — "
                . "jadwal_id=$jadwal_id sudah berstatus: {$jadwal['status']}");
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Maaf, slot ini baru saja diambil mahasiswa lain.';
        header('Location: /mahasiswa/dashboard.php');
        exit();
    }

    $pdo->prepare("
        INSERT INTO booking (mahasiswa_id, jadwal_id, topik, status)
        VALUES (:mahasiswa_id, :jadwal_id, :topik, 'pending')
    ")->execute([
        'mahasiswa_id' => $user['user_id'],
        'jadwal_id'    => $jadwal_id,
        'topik'        => htmlspecialchars($topik, ENT_QUOTES, 'UTF-8'),
    ]);

    $pdo->prepare("
        UPDATE jadwal SET status = 'booked' WHERE id = ?
    ")->execute([$jadwal_id]);

    $pdo->commit();

    // Get dosen_id for notification
    $stmt = $pdo->prepare("SELECT dosen_id FROM jadwal WHERE id = ?");
    $stmt->execute([$jadwal_id]);
    $jadwal_row = $stmt->fetch();

    if ($jadwal_row) {
        add_notifikasi(
            (int)$jadwal_row['dosen_id'],
            "Booking baru dari {$user['nama']}. Topik: " . substr($topik, 0, 50)
        );
    }

    $_SESSION['flash'] = 'Booking berhasil diajukan. Menunggu konfirmasi dosen.';

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("[BOOKING EXCEPTION] mahasiswa_id={$user['user_id']} "
            . "| jadwal_id=$jadwal_id | " . $e->getMessage());
    $_SESSION['flash_error'] = 'Terjadi kesalahan sistem. Silakan coba kembali.';

} finally {
    header('Location: /mahasiswa/dashboard.php');
    exit();
}