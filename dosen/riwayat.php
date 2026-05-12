<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('dosen');
$pdo = get_pdo();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Flash
$flash = $_SESSION['flash'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);

// Get filter params
$filter_status = $_GET['status'] ?? '';
$filter_tgl_mulai = $_GET['tgl_mulai'] ?? '';
$filter_tgl_akhir = $_GET['tgl_selesai'] ?? '';

// Build query
$sql = "
    SELECT
        b.id,
        b.topik,
        b.status,
        b.catatan_dosen,
        b.rating,
        b.created_at,
        b.approved_at,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        u.nama AS nama_mahasiswa,
        u.identifier AS nim
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    JOIN users u ON u.id = b.mahasiswa_id
    WHERE j.dosen_id = :dosen_id
";

$params = ['dosen_id' => $user['user_id']];

if ($filter_status) {
    $sql .= " AND b.status = :status";
    $params['status'] = $filter_status;
}

if ($filter_tgl_mulai) {
    $sql .= " AND j.tanggal >= :tgl_mulai";
    $params['tgl_mulai'] = $filter_tgl_mulai;
}

if ($filter_tgl_akhir) {
    $sql .= " AND j.tanggal <= :tgl_akhir";
    $params['tgl_akhir'] = $filter_tgl_akhir;
}

$sql .= " ORDER BY j.tanggal DESC, j.jam_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$riwayat = $stmt->fetchAll();

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=riwayat_konsultasi.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel

    fputcsv($output, ['ID', 'Mahasiswa', 'NIM', 'Tanggal', 'Waktu', 'Topik', 'Status', 'Catatan Dosen', 'Rating']);

    foreach ($riwayat as $r) {
        fputcsv($output, [
            $r['id'],
            $r['nama_mahasiswa'],
            $r['nim'],
            $r['tanggal'],
            $r['jam_mulai'] . ' - ' . $r['jam_selesai'],
            $r['topik'],
            $r['status'],
            $r['catatan_dosen'] ?? '',
            $r['rating'] ?? '',
        ]);
    }

    fclose($output);
    exit();
}

// Notification
$notif_count  = count_unread_notifikasi($user['user_id']);
$notif_list   = get_notifikasi($user['user_id']);
$mark_all_url = '/dosen/action_baca_notif.php';

// Stats
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN b.status = 'pending'  THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN b.status = 'selesai'  THEN 1 ELSE 0 END) as selesai
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    WHERE j.dosen_id = ?
");
$stmt->execute([$user['user_id']]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Konsultasi - Sistem Booking Konsultasi</title>
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
        }

        /* STATS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            text-align: center;
        }
        .stat-number { font-size: 1.5rem; font-weight: 700; color: var(--accent); }
        .stat-label { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.25rem; }

        /* FILTER & EXPORT */
        .action-bar {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .filter-group { display: flex; align-items: center; gap: 0.75rem; }
        .filter-input {
            padding: 0.6rem 0.75rem;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 0.875rem;
        }

        /* TABLE */
        .table-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            overflow: hidden;
        }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 1rem;
            color: var(--text-muted);
            font-size: 0.8125rem;
            font-weight: 600;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }
        td { padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.9375rem; }
        tr:last-child td { border-bottom: none; }

        /* BADGES */
        .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending  { background: rgba(245, 158, 11, 0.1); color: #fbbf24; }
        .badge-approved { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .badge-rejected { background: rgba(239, 68, 68, 0.1); color: #f87171; }
        .badge-finished { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }

        /* BUTTONS */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 0.5rem; padding: 0.6rem 1rem; border-radius: 8px;
            font-weight: 600; font-size: 0.875rem; cursor: pointer;
            transition: all 0.2s; border: none; text-decoration: none;
        }
        .btn-primary { background: var(--accent); color: #0f172a; }
        .btn-export { background: var(--green); color: white; }

        /* FLASH */
        .flash { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9375rem; }
        .flash-ok { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }

        .star-filled { color: var(--yellow); fill: var(--yellow); }
        .star-empty { color: var(--text-muted); }
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="/dosen/dashboard.php" class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Booking.</span>
    </a>
    <nav class="sidebar-nav">
        <a href="/dosen/dashboard.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Dashboard
        </a>
        <a href="/dosen/riwayat.php" class="nav-item active">
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
            <h1 style="font-size: 1.5rem; font-weight: 700;">Riwayat Konsultasi</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Daftar seluruh pertemuan yang telah dijadwalkan.</p>
        </div>
        <div class="user-profile">
            <?php include __DIR__ . '/../includes/notif_bell.php'; ?>
            <div class="avatar" style="background:#4F46E5">
                <?= strtoupper($user['nama'][0] ?? 'D') ?>
            </div>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-ok">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= (int)$stats['pending'] ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)$stats['approved'] ?></div>
            <div class="stat-label">Disetujui</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= (int)$stats['selesai'] ?></div>
            <div class="stat-label">Selesai</div>
        </div>
    </div>

    <form class="action-bar" method="GET">
        <div class="filter-group">
            <select name="status" class="filter-input">
                <option value="">Semua Status</option>
                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $filter_status === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                <option value="selesai" <?= $filter_status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
            </select>
            <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; padding: 0 0.75rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">Dari:</span>
                <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($filter_tgl_mulai) ?>" class="filter-input" style="border: none; background: transparent; padding: 0.6rem 0.25rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">Hingga:</span>
                <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($filter_tgl_akhir) ?>" class="filter-input" style="border: none; background: transparent; padding: 0.6rem 0.25rem;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                Filter
            </button>
            <?php if ($filter_status || $filter_tgl_mulai || $filter_tgl_akhir): ?>
                <a href="/dosen/riwayat.php" class="btn btn-outline btn-sm" style="color: var(--text-muted); border-color: var(--border);">Reset</a>
            <?php endif; ?>
        </div>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-export btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
            Export CSV
        </a>
    </form>

    <div class="table-card">
        <?php if (empty($riwayat)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem;">Belum ada riwayat booking.</div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Jadwal</th>
                            <th>Topik</th>
                            <th>Status</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $r): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?= htmlspecialchars($r['nama_mahasiswa']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($r['nim']) ?></div>
                            </td>
                            <td>
                                <div><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($r['jam_mulai']) ?> - <?= htmlspecialchars($r['jam_selesai']) ?></div>
                            </td>
                            <td style="max-width: 250px;"><?= htmlspecialchars($r['topik']) ?></td>
                            <td>
                                <?php
                                $badges = ['pending' => 'badge-pending', 'approved' => 'badge-approved', 'ditolak' => 'badge-rejected', 'selesai' => 'badge-finished'];
                                $labels = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'ditolak' => 'Ditolak', 'selesai' => 'Selesai'];
                                $badge_class = $badges[$r['status']] ?? 'badge-finished';
                                $badge_label = $labels[$r['status']] ?? $r['status'];
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $badge_label ?></span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 2px;">
                                    <?php if ($r['rating']): ?>
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="<?= $i <= $r['rating'] ? 'star-filled' : 'star-empty' ?>"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                        <?php endfor; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">Belum dinilai</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>