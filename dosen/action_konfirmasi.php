<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('dosen');
$pdo = get_pdo();

// CSRF
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    error_log("[CSRF] dosen_id={$user['user_id']} — konfirmasi booking.");
    exit('403 Forbidden.');
}

$booking_id = (int)($_POST['booking_id'] ?? 0);
$aksi       = $_POST['aksi'] ?? '';

if ($booking_id <= 0 || !in_array($aksi, ['approved', 'ditolak'], true)) {
    $_SESSION['flash_error'] = 'Parameter tidak valid.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// -----------------------------------------------------------
// VALIDASI KEPEMILIKAN MUTLAK
// Dosen A dilarang keras mengubah booking pada jadwal Dosen B
// JOIN booking → jadwal → verifikasi dosen_id
// -----------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT b.id, b.status, b.mahasiswa_id, j.dosen_id, j.id AS jadwal_id
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    WHERE b.id = :booking_id
      AND b.status = 'pending'
");
$stmt->execute(['booking_id' => $booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    error_log("[KONFIRMASI] booking_id=$booking_id tidak ditemukan atau bukan pending.");
    $_SESSION['flash_error'] = 'Booking tidak ditemukan.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// Cek kepemilikan — verifikasi dosen_id pada tabel jadwal
if ((int)$booking['dosen_id'] !== (int)$user['user_id']) {
    http_response_code(403);
    error_log("[OTORISASI] dosen_id={$user['user_id']} mencoba akses "
            . "booking_id=$booking_id milik dosen_id={$booking['dosen_id']}.");
    exit('403 Forbidden: Akses ditolak.');
}

// -----------------------------------------------------------
// UPDATE STATUS + jika ditolak, kembalikan slot ke 'tersedia'
// -----------------------------------------------------------
try {
    $pdo->beginTransaction();

    $catatan_dosen = trim($_POST['catatan_dosen'] ?? '');

    $pdo->prepare("
        UPDATE booking
        SET status         = :status,
            approved_at    = NOW(),
            catatan_dosen  = :catatan_dosen
        WHERE id = :id
    ")->execute([
        'status'        => $aksi,
        'catatan_dosen' => $catatan_dosen ? htmlspecialchars($catatan_dosen, ENT_QUOTES, 'UTF-8') : null,
        'id'            => $booking_id,
    ]);

    // Jika ditolak — bebaskan kembali slot jadwal
    if ($aksi === 'ditolak') {
        $pdo->prepare("
            UPDATE jadwal SET status = 'tersedia' WHERE id = ?
        ")->execute([$booking['jadwal_id']]);
    }

    $pdo->commit();

    // Notifikasi ke mahasiswa
    $pesan = $aksi === 'approved'
        ? "Booking #{$booking_id} disetujui. Silakan cek jadwal konsultasi."
        : "Booking #{$booking_id} ditolak.";
    add_notifikasi((int)$booking['mahasiswa_id'], $pesan);

    $_SESSION['flash'] = $aksi === 'approved'
        ? 'Booking berhasil disetujui.'
        : 'Booking telah ditolak dan slot dikembalikan.';

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("[KONFIRMASI ERROR] dosen_id={$user['user_id']} "
            . "| booking_id=$booking_id | " . $e->getMessage());
    $_SESSION['flash_error'] = 'Terjadi kesalahan sistem.';

} finally {
    header('Location: /dosen/dashboard.php');
    exit();
}