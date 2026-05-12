<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$user = require_role('dosen');
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403); exit('403 Forbidden.');
    }

    $jadwal_ids = $_POST['jadwal_ids'] ?? [];

    if (!empty($jadwal_ids) && is_array($jadwal_ids)) {
        // Sanitize IDs
        $ids = array_map('intval', $jadwal_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            // Hanya hapus yang statusnya 'tersedia' dan milik dosen ini
            $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id IN ($placeholders) AND dosen_id = ? AND status = 'tersedia'");
            $stmt->execute(array_merge($ids, [$user['user_id']]));
            
            $count = $stmt->rowCount();
            if ($count > 0) {
                $_SESSION['flash'] = "$count slot jadwal berhasil dihapus.";
            } else {
                $_SESSION['flash_error'] = "Tidak ada slot yang dapat dihapus (mungkin sudah dibooking).";
            }
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Gagal menghapus jadwal.";
        }
    } else {
        $_SESSION['flash_error'] = "Pilih jadwal yang ingin dihapus.";
    }
}

header('Location: /dosen/dashboard.php');
exit();
