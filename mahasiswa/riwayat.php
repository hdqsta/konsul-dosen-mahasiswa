<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('mahasiswa');
$pdo = get_pdo();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        u.nama AS nama_dosen,
        u.identifier AS nip
    FROM booking b
    JOIN jadwal j ON j.id = b.jadwal_id
    JOIN users u ON u.id = j.dosen_id
    WHERE b.mahasiswa_id = :mhs_id
";

$params = ['mhs_id' => $user['user_id']];

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

$notif_count  = count_unread_notifikasi($user['user_id']);
$notif_list   = get_notifikasi($user['user_id']);
$mark_all_url = '/mahasiswa/action_baca_notif.php';

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
            --bg-base   : #0f172a; --bg-sidebar: #1e293b; --bg-card   : #1e293b; --bg-input  : #0f172a;
            --border    : #334155; --text-main : #f1f5f9; --text-muted: #94a3b8; --accent    : #38bdf8;
            --accent-hover : #0ea5e9; --green     : #10b981; --yellow    : #f59e0b; --red       : #ef4444;
            --radius    : 12px; --sidebar-width: 260px;
        }
        body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-base); color: var(--text-main); min-height: 100vh; display: flex; }
        
        .sidebar { width: var(--sidebar-width); background: var(--bg-sidebar); border-right: 1px solid var(--border); height: 100vh; position: sticky; top: 0; display: flex; flex-direction: column; padding: 1.5rem; flex-shrink: 0; }
        .sidebar-brand { display: flex; align-items: center; gap: 0.75rem; font-weight: 700; font-size: 1.25rem; color: var(--accent); margin-bottom: 2.5rem; text-decoration: none; }
        .sidebar-nav { display: flex; flex-direction: column; gap: 0.5rem; flex: 1; }
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--text-muted); text-decoration: none; border-radius: 8px; font-size: 0.95rem; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { background: rgba(56, 189, 248, 0.1); color: var(--accent); }
        .sidebar-footer { padding-top: 1.5rem; border-top: 1px solid var(--border); }
        
        .main-content { flex: 1; padding: 2rem; max-width: 1200px; margin: 0 auto; width: 100%; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .user-profile { display: flex; align-items: center; gap: 1rem; }
        .avatar { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; }

        .action-bar { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-bottom: 2rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; }
        .filter-group { display: flex; align-items: center; gap: 0.75rem; }
        .filter-input { padding: 0.6rem 0.75rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); font-size: 0.875rem; }
        
        .table-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; overflow: hidden; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; color: var(--text-muted); font-size: 0.8125rem; font-weight: 600; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.9375rem; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-pending  { background: rgba(245, 158, 11, 0.1); color: #fbbf24; }
        .badge-approved { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .badge-rejected { background: rgba(239, 68, 68, 0.1); color: #f87171; }
        .badge-finished { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }
        .badge-cancel   { background: rgba(139, 92, 246, 0.1); color: #a78bfa; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.6rem 1rem; border-radius: 8px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s; border: none; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #0f172a; }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.75rem; }
        
        .flash { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9375rem; }
        .flash-ok { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
        .flash-err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }

        /* RATING STARS CSS */
        .rating { display: inline-flex; flex-direction: row-reverse; gap: 2px; }
        .rating input { display: none; }
        .rating label { cursor: pointer; color: var(--text-muted); transition: color 0.2s; }
        .rating label svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .rating input:checked ~ label, .rating label:hover, .rating label:hover ~ label { color: var(--yellow); }
        .rating input:checked ~ label svg, .rating label:hover svg, .rating label:hover ~ label svg { fill: var(--yellow); }
        .star-filled { color: var(--yellow); fill: var(--yellow); width: 14px; height: 14px; display: inline-block; }
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="/mahasiswa/dashboard.php" class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Booking.</span>
    </a>
    <nav class="sidebar-nav">
        <a href="/mahasiswa/dashboard.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Dashboard
        </a>
        <a href="/mahasiswa/riwayat.php" class="nav-item active">
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
            <h1 style="font-size: 1.5rem; font-weight: 700;">Riwayat Booking Saya</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Pantau status pengajuan konsultasi Anda.</p>
        </div>
        <div class="user-profile">
            <?php include __DIR__ . '/../includes/notif_bell.php'; ?>
            <div class="avatar" style="background:<?= htmlspecialchars(get_avatar_color($user['nama'])) ?>">
                <?= htmlspecialchars(get_initials($user['nama'])) ?>
            </div>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-ok"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> <?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="flash flash-err"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg> <?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <form class="action-bar" method="GET">
        <div class="filter-group">
            <select name="status" class="filter-input">
                <option value="">Semua Status</option>
                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $filter_status === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                <option value="selesai" <?= $filter_status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                <option value="dibatalkan" <?= $filter_status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
            <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; padding: 0 0.75rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">Dari:</span>
                <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($filter_tgl_mulai) ?>" class="filter-input" style="border: none; background: transparent; padding: 0.6rem 0.25rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">Hingga:</span>
                <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($filter_tgl_akhir) ?>" class="filter-input" style="border: none; background: transparent; padding: 0.6rem 0.25rem;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($filter_status || $filter_tgl_mulai || $filter_tgl_akhir): ?>
                <a href="/mahasiswa/riwayat.php" class="btn btn-outline btn-sm" style="color: var(--text-muted); border-color: var(--border);">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-card">
        <?php if (empty($riwayat)): ?>
            <div style="text-align: center; color: var(--text-muted); padding: 3rem;">Belum ada riwayat booking.</div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Dosen</th>
                            <th>Jadwal</th>
                            <th>Topik & Catatan</th>
                            <th>Status</th>
                            <th>Aksi & Penilaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $r): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.6rem;">
                                    <div class="avatar" style="width:28px; height:28px; font-size:0.65rem; background:<?= htmlspecialchars(get_avatar_color($r['nama_dosen'])) ?>">
                                        <?= htmlspecialchars(get_initials($r['nama_dosen'])) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($r['nama_dosen']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?= date('d M Y', strtotime($r['tanggal'])) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($r['jam_mulai']) ?> - <?= htmlspecialchars($r['jam_selesai']) ?></div>
                            </td>
                            <td style="max-width: 250px;">
                                <div style="font-weight:500; margin-bottom: 0.25rem;"><?= htmlspecialchars($r['topik']) ?></div>
                                <?php if (!empty($r['catatan_dosen'])): ?>
                                    <div style="font-size: 0.8125rem; color: var(--text-muted); background: var(--bg-input); padding: 0.5rem; border-radius: 6px; border: 1px solid var(--border);">
                                        <strong>Catatan dosen:</strong><br><?= nl2br(htmlspecialchars($r['catatan_dosen'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badges = ['pending' => 'badge-pending', 'approved' => 'badge-approved', 'ditolak' => 'badge-rejected', 'selesai' => 'badge-finished', 'dibatalkan' => 'badge-cancel'];
                                $labels = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'ditolak' => 'Ditolak', 'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
                                $badge_class = $badges[$r['status']] ?? 'badge-finished';
                                $badge_label = $labels[$r['status']] ?? $r['status'];
                                ?>
                                <span class="badge <?= $badge_class ?>"><?= $badge_label ?></span>
                            </td>
                            <td style="min-width: 180px;">
                                <div style="display:flex; flex-direction:column; gap:0.5rem; align-items:flex-start;">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <form method="POST" action="/mahasiswa/action_batal.php" onsubmit="return confirm('Yakin ingin membatalkan?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--red); border-color: rgba(239, 68, 68, 0.2);">Batalkan Pengajuan</button>
                                        </form>
                                    <?php elseif (in_array($r['status'], ['approved', 'selesai'])): ?>
                                        <a href="/booking/cetak.php?id=<?= (int)$r['id'] ?>" target="_blank" class="btn btn-outline btn-sm" style="color: var(--accent); border-color: var(--accent);">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:4px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></svg>
                                            Cetak Bukti
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($r['status'] === 'selesai'): ?>
                                        <?php if ($r['rating']): ?>
                                            <div style="display: flex; gap: 2px; margin-top: 4px;">
                                                <?php for($i=1; $i<=5; $i++): ?>
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="star-filled" style="<?= $i > $r['rating'] ? 'fill:none;stroke:currentColor;color:var(--text-muted)' : '' ?>"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                                                <?php endfor; ?>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" action="/mahasiswa/action_rating.php" style="margin-top:4px;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
                                                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:2px;">Beri Penilaian:</div>
                                                <div class="rating">
                                                    <input type="radio" id="star5-<?= $r['id'] ?>" name="rating" value="5" onchange="this.form.submit()" /><label for="star5-<?= $r['id'] ?>"><svg viewBox="0 0 24 24"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                                                    <input type="radio" id="star4-<?= $r['id'] ?>" name="rating" value="4" onchange="this.form.submit()" /><label for="star4-<?= $r['id'] ?>"><svg viewBox="0 0 24 24"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                                                    <input type="radio" id="star3-<?= $r['id'] ?>" name="rating" value="3" onchange="this.form.submit()" /><label for="star3-<?= $r['id'] ?>"><svg viewBox="0 0 24 24"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                                                    <input type="radio" id="star2-<?= $r['id'] ?>" name="rating" value="2" onchange="this.form.submit()" /><label for="star2-<?= $r['id'] ?>"><svg viewBox="0 0 24 24"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                                                    <input type="radio" id="star1-<?= $r['id'] ?>" name="rating" value="1" onchange="this.form.submit()" /><label for="star1-<?= $r['id'] ?>"><svg viewBox="0 0 24 24"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                                                </div>
                                            </form>
                                        <?php endif; ?>
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
