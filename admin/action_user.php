<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/dashboard.php');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Token keamanan tidak valid.';
    header('Location: /admin/dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = get_pdo();

if ($action === 'add') {
    $identifier = trim($_POST['identifier'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $role = $_POST['role'] ?? '';
    $raw_password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($nama) || !in_array($role, ['mahasiswa', 'dosen'])) {
        $_SESSION['flash_error'] = 'Data tidak lengkap atau tidak valid.';
    } elseif (!empty($raw_password) && strlen($raw_password) < 6) {
        $_SESSION['flash_error'] = 'Password harus memiliki minimal 6 karakter.';
    } else {
        // Jika password kosong, gunakan default
        $final_password = empty($raw_password) ? 'password123' : $raw_password;
        $password_hash = password_hash($final_password, PASSWORD_BCRYPT, ['cost' => 12]);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (identifier, nama, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$identifier, $nama, $password_hash, $role]);
            $_SESSION['flash'] = "Berhasil menambahkan $role baru: $nama.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation
                $_SESSION['flash_error'] = "Gagal: identifier '$identifier' sudah terdaftar.";
            } else {
                $_SESSION['flash_error'] = "Terjadi kesalahan sistem.";
            }
        }
    }
} elseif ($action === 'toggle_active') {
    $target_user_id = (int)($_POST['user_id'] ?? 0);

    if ($target_user_id === $user['user_id']) {
        $_SESSION['flash_error'] = 'Anda tidak dapat menonaktifkan akun sendiri.';
    } else {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $target = $stmt->fetch();

        if ($target) {
            $new_status = $target['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $target_user_id]);

            $status_str = $new_status ? 'diaktifkan' : 'dinonaktifkan';
            $_SESSION['flash'] = "Akun berhasil $status_str.";
        } else {
            $_SESSION['flash_error'] = 'Pengguna tidak ditemukan.';
        }
    }
}

header('Location: /admin/dashboard.php');
exit;
