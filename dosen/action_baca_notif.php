<?php
// Mark notifikasi sudah dibaca (untuk dosen)
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('dosen');

$notifikasi_id = (int)($_POST['notifikasi_id'] ?? 0);

if ($notifikasi_id > 0) {
    mark_notifikasi_read($notifikasi_id, $user['user_id']);
} else {
    mark_all_notifikasi_read($user['user_id']);
}

$redirect = $_POST['redirect'] ?? '/dosen/dashboard.php';
header('Location: ' . $redirect);
exit();
