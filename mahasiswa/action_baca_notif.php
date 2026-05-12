<?php
// Mark notifikasi sudah dibaca
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('mahasiswa');

$notifikasi_id = (int)($_POST['notifikasi_id'] ?? 0);

// Mark single as read
if ($notifikasi_id > 0) {
    mark_notifikasi_read($notifikasi_id, $user['user_id']);
} else {
    // Mark all as read
    mark_all_notifikasi_read($user['user_id']);
}

// Redirect back
$redirect = $_POST['redirect'] ?? '/mahasiswa/dashboard.php';
header('Location: ' . $redirect);
exit();