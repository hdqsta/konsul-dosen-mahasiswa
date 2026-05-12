<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('dosen');
$pdo = get_pdo();

// Auto-cleanup jadwal kadaluarsa
$pdo->prepare("UPDATE jadwal SET status = 'dibatalkan' WHERE status = 'tersedia' AND tanggal < CURDATE()")->execute();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Flash
$flash       = $_SESSION['flash']       ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);

// -----------------------------------------------------------
// QUERY: booking pending pada jadwal milik dosen ini
// -----------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT
        b.id          AS booking_id,
        b.topik,
        b.created_at,
        j.id          AS jadwal_id,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        u.nama        AS nama_mahasiswa,
        u.identifier  AS nim
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    JOIN users  u ON u.id = b.mahasiswa_id
    WHERE j.dosen_id   = :dosen_id
      AND b.status     = 'pending'
    ORDER BY j.tanggal ASC, j.jam_mulai ASC
");
$stmt->execute(['dosen_id' => $user['user_id']]);
$pending_list = $stmt->fetchAll();

// -----------------------------------------------------------
// QUERY: jadwal milik dosen ini (7 hari ke depan)
// -----------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT id, tanggal, jam_mulai, jam_selesai, status
    FROM jadwal
    WHERE dosen_id = :dosen_id
      AND tanggal >= CURDATE()
      AND tanggal <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY tanggal ASC, jam_mulai ASC
");
$stmt->execute(['dosen_id' => $user['user_id']]);
$my_jadwal = $stmt->fetchAll();

// Stats
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN b.status = 'pending'  THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN b.status = 'selesai'  THEN 1 ELSE 0 END) as selesai,
        AVG(CASE WHEN b.status = 'selesai' AND b.rating IS NOT NULL THEN b.rating ELSE NULL END) as avg_rating
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    WHERE j.dosen_id = ?
");
$stmt->execute([$user['user_id']]);
$stats = $stmt->fetch();

// Notification
$notif_count  = count_unread_notifikasi($user['user_id']);
$notif_list   = get_notifikasi($user['user_id']);
$mark_all_url = '/dosen/action_baca_notif.php';

// Get approved bookings for "tandai selesai"
$stmt = $pdo->prepare("
    SELECT
        b.id AS booking_id,
        b.topik,
        b.created_at,
        j.id AS jadwal_id,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        u.nama AS nama_mahasiswa,
        u.identifier AS nim
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    JOIN users u ON u.id = b.mahasiswa_id
    WHERE j.dosen_id = ?
      AND b.status = 'approved'
    ORDER BY j.tanggal ASC, j.jam_mulai ASC
");
$stmt->execute([$user['user_id']]);
$approved_list = $stmt->fetchAll();

function get_initials(string $nama): string {
    $words = array_filter(explode(' ', trim($nama)));
    $init  = '';
    foreach (array_slice(array_values($words), 0, 2) as $w) {
        $init .= strtoupper($w[0]);
    }
    return $init;
}
function get_avatar_color(string $nama): string {
    $colors = ['#4F46E5','#7C3AED','#059669','#DC2626','#D97706','#0891B2'];
    $firstChar = !empty($nama) ? ord($nama[0]) : 0;
    return $colors[$firstChar % count($colors)];
}
function status_badge(string $status): string {
    $map = [
        'tersedia'   => ['#059669','#D1FAE5','Tersedia'],
        'booked'     => ['#D97706','#FEF3C7','Booked'],
        'dibatalkan' => ['#DC2626','#FEE2E2','Dibatalkan'],
    ];
    $s = $map[$status] ?? ['#6B7280','#F3F4F6',$status];
    return "<span style='color:{$s[0]};background:{$s[1]};
                padding:2px 10px;border-radius:99px;
                font-size:0.75rem;font-weight:600;'>{$s[2]}</span>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - Sistem Booking Konsultasi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg-base   : #0f172a;
            --bg-sidebar: #1e293b;
            --bg-card   : #1e293b;
            --bg-input  : #0f172a;
            --border    : #334155;
            --text-main : #f1f5f9;
            --text-muted: #94a3b8;
            --accent    : #38bdf8;
            --accent-hover : #0ea5e9;
            --green     : #10b981;
            --yellow    : #f59e0b;
            --red       : #ef4444;
            --radius    : 12px;
            --sidebar-width: 260px;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-base);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        /* SIDEBAR */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            height: 100vh;
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            flex-shrink: 0;
        }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--accent);
            margin-bottom: 2.5rem;
            text-decoration: none;
        }
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(56, 189, 248, 0.1);
            color: var(--accent);
        }
        .sidebar-footer {
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        /* DASHBOARD GRID */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 2rem;
        }
        @media (max-width: 1024px) {
            .dashboard-layout { grid-template-columns: 1fr; }
        }

        .section-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* TABLES */
        .table-container { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 1rem;
            color: var(--text-muted);
            font-size: 0.8125rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9375rem;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }

        /* BADGES */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: #fbbf24; }
        .badge-danger  { background: rgba(239, 68, 68, 0.1); color: #f87171; }

        /* FORMS */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 0.9375rem;
        }
        .form-group input:focus { outline: none; border-color: var(--accent); }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: var(--accent); color: #0f172a; }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
        .btn-outline:hover { background: rgba(255,255,255,0.05); }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.75rem; }

        /* BOOKING CARD */
        .booking-card {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            background: rgba(255,255,255,0.02);
        }
        .booking-card .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .booking-card .topik {
            background: var(--bg-input);
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1rem;
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .booking-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        /* NOTIFICATION */
        .flash {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9375rem;
        }
        .flash-ok { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
        .flash-err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="/dosen/dashboard.php" class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Booking.</span>
    </a>
    <nav class="sidebar-nav">
        <a href="/dosen/dashboard.php" class="nav-item active">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Dashboard
        </a>
        <a href="/dosen/riwayat.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Riwayat
        </a>
        <a href="/profil.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profil
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="/logout.php" class="nav-item" style="color: var(--red)">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
            Keluar
        </a>
    </div>
</aside>

<main class="main-content">
    <header class="header">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700;">Halo, <?= htmlspecialchars($user['nama']) ?></h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Pantau dan kelola jadwal konsultasi Anda hari ini.</p>
        </div>
        <div class="user-profile">
            <?php include __DIR__ . '/../includes/notif_bell.php'; ?>
            <div class="avatar" style="background:<?= htmlspecialchars(get_avatar_color($user['nama'])) ?>">
                <?= htmlspecialchars(get_initials($user['nama'])) ?>
            </div>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-ok">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?= htmlspecialchars($flash) ?></span>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="flash flash-err">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>
            <span><?= htmlspecialchars($flash_error) ?></span>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-label">Pending Approval</span>
            <span class="stat-value" style="color: var(--yellow)"><?= (int)$stats['pending'] ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Sedang Berjalan</span>
            <span class="stat-value" style="color: var(--accent)"><?= (int)$stats['approved'] ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Total Selesai</span>
            <span class="stat-value" style="color: var(--green)"><?= (int)$stats['selesai'] ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Rating Rata-rata</span>
            <span class="stat-value" style="color: var(--purple, #8b5cf6)"><?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '-' ?></span>
        </div>
    </div>

    <div class="dashboard-layout">
        <div class="left-col">
            <section class="section-card">
                <div class="section-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span>Generate Slot Jadwal</span>
                    </div>
                </div>
                <form method="POST" action="/dosen/action_generate_jadwal.php" id="form-generate">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="date" name="tanggal" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>Durasi per Slot</label>
                            <select name="durasi">
                                <option value="15">15 menit</option>
                                <option value="30" selected>30 menit</option>
                                <option value="45">45 menit</option>
                                <option value="60">60 menit</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jam Mulai</label>
                            <input type="time" name="jam_mulai" required value="08:00">
                        </div>
                        <div class="form-group">
                            <label>Jam Selesai</label>
                            <input type="time" name="jam_selesai" required value="12:00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan (opsional)</label>
                        <input type="text" name="catatan_slot" placeholder="Contoh: Ruang tamu, bawa laptop">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%">Buat Jadwal Otomatis</button>
                </form>
            </section>

            <section class="section-card">
                <div class="section-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                        <span>Jadwal Saya (7 Hari ke Depan)</span>
                    </div>
                    <?php if (!empty($my_jadwal)): ?>
                        <button type="button" id="btn-bulk-delete" class="btn btn-outline btn-sm" style="color: var(--red); border-color: rgba(239, 68, 68, 0.2); display: none;" onclick="confirmBulkDelete()">Hapus Terpilih</button>
                    <?php endif; ?>
                </div>
                <?php if (empty($my_jadwal)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 2rem;">Belum ada jadwal yang dibuat.</p>
                <?php else: ?>
                    <form id="form-bulk-delete" method="POST" action="/dosen/action_hapus_massal.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)" style="cursor: pointer;">
                                        </th>
                                        <th>Tanggal</th>
                                        <th>Waktu</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($my_jadwal as $j): ?>
                                    <tr>
                                        <td>
                                            <?php if ($j['status'] === 'tersedia'): ?>
                                                <input type="checkbox" name="jadwal_ids[]" value="<?= (int)$j['id'] ?>" class="slot-checkbox" onclick="updateBulkBtn()" style="cursor: pointer;">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d M Y', strtotime($j['tanggal'])) ?></td>
                                        <td><?= htmlspecialchars($j['jam_mulai']) ?> - <?= htmlspecialchars($j['jam_selesai']) ?></td>
                                        <td>
                                            <?php if ($j['status'] === 'tersedia'): ?>
                                                <span class="badge badge-success">Tersedia</span>
                                            <?php elseif ($j['status'] === 'booked'): ?>
                                                <span class="badge badge-warning">Booked</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Dibatalkan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($j['status'] === 'tersedia'): ?>
                                                <button type="button" class="btn btn-outline btn-sm" style="color: var(--red); border-color: rgba(239, 68, 68, 0.2)" onclick="deleteSingle(<?= (int)$j['id'] ?>)">Hapus</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>

                    <form id="form-delete-single" method="POST" action="/dosen/action_hapus_slot.php" style="display:none;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="jadwal_id" id="delete-single-id">
                    </form>
                <?php endif; ?>
            </section>
        </div>

        <div class="right-col">
            <section class="section-card">
                <div class="section-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <span>Pengajuan Masuk</span>
                    </div>
                    <?php if (!empty($pending_list)): ?>
                        <span class="badge" style="background: var(--red); color: white;"><?= count($pending_list) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($pending_list)): ?>
                    <p style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Tidak ada pengajuan baru.</p>
                <?php else: ?>
                    <?php foreach ($pending_list as $b): ?>
                    <div class="booking-card">
                        <div class="user-info">
                            <div class="avatar" style="background:<?= htmlspecialchars(get_avatar_color($b['nama_mahasiswa'])) ?>; width: 32px; height: 32px; font-size: 0.7rem;">
                                <?= htmlspecialchars(get_initials($b['nama_mahasiswa'])) ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($b['nama_mahasiswa']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($b['nim']) ?></div>
                            </div>
                        </div>
                        <div style="font-size: 0.8125rem; color: var(--text-muted); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                            <?= date('d M', strtotime($b['tanggal'])) ?> | 
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?= htmlspecialchars($b['jam_mulai']) ?> - <?= htmlspecialchars($b['jam_selesai']) ?>
                        </div>
                        <div class="topik"><?= htmlspecialchars($b['topik']) ?></div>
                        <div class="booking-actions">
                            <form method="POST" action="/dosen/action_konfirmasi.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                <input type="hidden" name="aksi" value="approved">
                                <button type="submit" class="btn btn-primary btn-sm" style="width: 100%; background: var(--green); color: white;">Setujui</button>
                            </form>
                            <form method="POST" action="/dosen/action_konfirmasi.php">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                                <input type="hidden" name="aksi" value="ditolak">
                                <button type="submit" class="btn btn-outline btn-sm" style="width: 100%; color: var(--red); border-color: rgba(239, 68, 68, 0.2)">Tolak</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <?php if (!empty($approved_list)): ?>
            <section class="section-card">
                <div class="section-title">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span>Sedang Berjalan</span>
                    </div>
                </div>
                <?php foreach ($approved_list as $b): ?>
                <div class="booking-card">
                    <div class="user-info">
                        <div class="avatar" style="background:<?= htmlspecialchars(get_avatar_color($b['nama_mahasiswa'])) ?>; width: 32px; height: 32px; font-size: 0.7rem;">
                            <?= htmlspecialchars(get_initials($b['nama_mahasiswa'])) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($b['nama_mahasiswa']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($b['nim']) ?></div>
                        </div>
                    </div>
                    <form method="POST" action="/dosen/action_selesai.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                        <div class="form-group">
                            <textarea name="catatan_dosen" placeholder="Catatan konsultasi..." style="font-size: 0.8125rem; min-height: 60px;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="width: 100%">Tandai Selesai</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    document.getElementById('form-generate').addEventListener('submit', function() {
        this.querySelector('button[type=submit]').disabled = true;
        this.querySelector('button[type=submit]').textContent = 'Memproses...';
    });

    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.slot-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        updateBulkBtn();
    }

    function updateBulkBtn() {
        const anyChecked = document.querySelectorAll('.slot-checkbox:checked').length > 0;
        document.getElementById('btn-bulk-delete').style.display = anyChecked ? 'block' : 'none';
    }

    function confirmBulkDelete() {
        if (confirm('Hapus semua slot terpilih?')) {
            document.getElementById('form-bulk-delete').submit();
        }
    }

    function deleteSingle(id) {
        if (confirm('Hapus slot ini?')) {
            document.getElementById('delete-single-id').value = id;
            document.getElementById('form-delete-single').submit();
        }
    }
</script>

</body>
</html>