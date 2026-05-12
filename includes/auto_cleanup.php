<?php
// Auto-cleanup jadwal kadaluarsa
// Executed saat database connection pertama kali dibuat

function auto_cleanup_jadwal_kadaluarsa(): void {
    $pdo = get_pdo();

    // Update jadwal yang tanggalnya sudah lewat dan statusnya bukan 'dibatalkan'
    // Jangan hapus, cukup ubat status jadi 'dibatalkan'
    $sql = "UPDATE jadwal
            SET status = 'dibatalkan'
            WHERE tanggal < CURDATE()
              AND status IN ('tersedia', 'booked')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}