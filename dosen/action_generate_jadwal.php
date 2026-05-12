<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$user = require_role('dosen');
$pdo = get_pdo();

// CSRF
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    error_log("[CSRF] dosen_id={$user['user_id']} — generate jadwal.");
    exit('403 Forbidden.');
}

$tanggal      = $_POST['tanggal']      ?? '';
$jam_mulai    = $_POST['jam_mulai']    ?? '';
$jam_selesai  = $_POST['jam_selesai']  ?? '';
$durasi       = (int)($_POST['durasi'] ?? 30);
$catatan_slot = trim($_POST['catatan_slot'] ?? '');

// Validasi dasar
if (!$tanggal || !$jam_mulai || !$jam_selesai || $durasi <= 0) {
    $_SESSION['flash_error'] = 'Input tidak lengkap.';
    header('Location: /dosen/dashboard.php');
    exit();
}

if ($jam_selesai <= $jam_mulai) {
    $_SESSION['flash_error'] = 'Jam selesai harus lebih besar dari jam mulai.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// Generate slots dengan DateInterval
$start    = new DateTime("$tanggal $jam_mulai");
$end      = new DateTime("$tanggal $jam_selesai");
$interval = new DateInterval("PT{$durasi}M");

$slots   = [];
$current = clone $start;

while ($current < $end) {
    $next = clone $current;
    $next->add($interval);
    if ($next > $end) break;

    $slots[] = [
        $user['user_id'],
        $tanggal,
        $current->format('H:i:s'),
        $next->format('H:i:s'),
        $catatan_slot ?: null,
    ];

    $current->add($interval);
}

if (empty($slots)) {
    $_SESSION['flash_error'] = 'Rentang waktu terlalu sempit untuk durasi yang dipilih.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// Filter duplikat pada array memori sebelum eksekusi batch
$seen    = [];
$unique  = [];
foreach ($slots as $slot) {
    $key = $slot[1] . '|' . $slot[2]; // tanggal|jam_mulai
    if (!isset($seen[$key])) {
        $seen[$key]    = true;
        $unique[]      = $slot;
    }
}

if (empty($unique)) {
    $_SESSION['flash_error'] = 'Tidak ada slot unik untuk disimpan.';
    header('Location: /dosen/dashboard.php');
    exit();
}

// BATCH INSERT dengan transaction untuk atomicity
$placeholders = implode(', ', array_fill(0, count($unique), '(?, ?, ?, ?, ?)'));
$values       = array_merge(...$unique);

$sql = "INSERT INTO jadwal (dosen_id, tanggal, jam_mulai, jam_selesai, catatan_slot)
        VALUES $placeholders"
        . " ON DUPLICATE KEY UPDATE catatan_slot = VALUES(catatan_slot)";

try {
    $pdo->beginTransaction();
    $pdo->prepare($sql)->execute($values);
    $pdo->commit();
    $_SESSION['flash'] = count($unique) . " slot berhasil dibuat.";

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("[GENERATE ERROR] " . $e->getMessage());
    $_SESSION['flash_error'] = 'Terjadi kesalahan sistem.';
} finally {
    header('Location: /dosen/dashboard.php');
    exit();
}