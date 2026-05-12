<?php
// Helper functions untuk notifikasi in-app

/**
 * Ambil notifikasi untuk user tertentu
 */
function get_notifikasi(int $user_id, bool $unread_only = false): array {
    $pdo = get_pdo();

    $sql = "SELECT * FROM notifikasi WHERE user_id = ?";
    $params = [$user_id];

    if ($unread_only) {
        $sql .= " AND is_read = 0";
    }

    $sql .= " ORDER BY created_at DESC LIMIT 20";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Hitung notifikasi belum dibaca
 */
function count_unread_notifikasi(int $user_id): int {
    $pdo = get_pdo();

    $sql = "SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);

    return (int) $stmt->fetchColumn();
}

/**
 * Tambah notifikasi baru
 */
function add_notifikasi(int $user_id, string $pesan): bool {
    $pdo = get_pdo();

    $sql = "INSERT INTO notifikasi (user_id, pesan) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([$user_id, $pesan]);
}

/**
 * Tandai notifikasi sudah dibaca
 */
function mark_notifikasi_read(int $notifikasi_id, int $user_id): bool {
    $pdo = get_pdo();

    $sql = "UPDATE notifikasi SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([$notifikasi_id, $user_id]);
}

/**
 * Tandai semua notifikasi user sudah dibaca
 */
function mark_all_notifikasi_read(int $user_id): bool {
    $pdo = get_pdo();

    $sql = "UPDATE notifikasi SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $pdo->prepare($sql);

    return $stmt->execute([$user_id]);
}

/**
 * Kirim notifikasi ke multiple user
 */
function broadcast_notifikasi(array $user_ids, string $pesan): bool {
    $pdo = get_pdo();

    $success = true;
    foreach ($user_ids as $user_id) {
        if (!add_notifikasi((int) $user_id, $pesan)) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Format timestamp ke waktu relatif (e.g. "2 menit lalu")
 */
function time_ago(string $datetime): string {
    $diff = (new DateTime())->getTimestamp() - (new DateTime($datetime))->getTimestamp();
    if ($diff < 60)     return 'Baru saja';
    if ($diff < 3600)   return (int)($diff / 60) . ' menit lalu';
    if ($diff < 86400)  return (int)($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return (int)($diff / 86400) . ' hari lalu';
    return (new DateTime($datetime))->format('d M Y');
}