<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifikasi.php';

$user = require_role('mahasiswa');
$pdo = get_pdo();

// -----------------------------------------------------------
// AUTO-CLEANUP JADWAL KADALUARSA
// -----------------------------------------------------------
$pdo->prepare("UPDATE jadwal SET status = 'dibatalkan' WHERE status = 'tersedia' AND tanggal < CURDATE()")->execute();

// -----------------------------------------------------------
// CSRF TOKEN
// -----------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// -----------------------------------------------------------
// FLASH MESSAGE
// -----------------------------------------------------------
$flash       = $_SESSION['flash']       ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);

// -----------------------------------------------------------
// QUERY: slot tersedia + filter
// -----------------------------------------------------------
$filter_dosen = $_GET['dosen'] ?? '';
$search_dosen = $_GET['search'] ?? '';

$sql = "
    SELECT
        j.id,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        j.catatan_slot,
        u.id AS dosen_id,
        u.nama AS nama_dosen
    FROM jadwal j
    JOIN users u ON u.id = j.dosen_id
    WHERE j.status = 'tersedia'
      AND j.tanggal >= CURDATE()
      AND j.tanggal <= DATE_ADD(CURDATE(), INTERVAL 6 DAY)
";

if ($filter_dosen) {
    $sql .= " AND u.id = :dosen_id";
}
if ($search_dosen) {
    $sql .= " AND u.nama LIKE :search";
}

$sql .= " ORDER BY j.tanggal ASC, j.jam_mulai ASC";

$stmt = $pdo->prepare($sql);
if ($filter_dosen) {
    $stmt->bindValue(':dosen_id', (int)$filter_dosen, PDO::PARAM_INT);
}
if ($search_dosen) {
    $stmt->bindValue(':search', '%' . $search_dosen . '%');
}
$stmt->execute();
$slots = $stmt->fetchAll();

// Grouping slots by date for the calendar
$calendar = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $calendar[$date] = [];
}
foreach ($slots as $slot) {
    if (isset($calendar[$slot['tanggal']])) {
        $calendar[$slot['tanggal']][] = $slot;
    }
}

// Get list dosen for filter
$stmt = $pdo->query("SELECT id, nama FROM users WHERE role = 'dosen' AND is_active = 1 ORDER BY nama");
$dosens = $stmt->fetchAll();

// Stats
$stmt = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved'   THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'ditolak'    THEN 1 ELSE 0 END) as ditolak,
        SUM(CASE WHEN status = 'selesai'    THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN status = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan
    FROM booking
    WHERE mahasiswa_id = ?
");
$stmt->execute([$user['user_id']]);
$stats = $stmt->fetch();

// Notification
$notif_count   = count_unread_notifikasi($user['user_id']);
$notif_list    = get_notifikasi($user['user_id']);
$mark_all_url  = '/mahasiswa/action_baca_notif.php';

// Helpers
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

$hari_indo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - Sistem Booking Konsultasi</title>
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
        
        .main-content { flex: 1; padding: 2rem; max-width: 1200px; margin: 0 auto; width: 100%; overflow: hidden; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .user-profile { display: flex; align-items: center; gap: 1rem; }
        .avatar { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; text-align: center; }
        .stat-label { color: var(--text-muted); font-size: 0.8125rem; font-weight: 500; margin-bottom: 0.25rem; }
        .stat-value { font-size: 1.5rem; font-weight: 700; }

        .filter-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1rem; margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;}
        .filter-group { display: flex; align-items: center; gap: 0.75rem; flex: 1;}
        .filter-input { padding: 0.6rem 0.75rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); font-size: 0.875rem; }
        .filter-input:focus { outline: none; border-color: var(--accent); }

        /* CALENDAR CSS */
        .calendar-scroll {
            display: flex;
            gap: 1.25rem;
            overflow-x: auto;
            padding-bottom: 1.5rem;
            scrollbar-width: thin;
            scrollbar-color: var(--border) transparent;
        }
        .calendar-scroll::-webkit-scrollbar { height: 8px; }
        .calendar-scroll::-webkit-scrollbar-track { background: transparent; }
        .calendar-scroll::-webkit-scrollbar-thumb { background-color: var(--border); border-radius: 20px; }
        
        .calendar-day {
            flex: 0 0 320px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            display: flex;
            flex-direction: column;
            min-height: 400px;
        }
        .calendar-day.today { border-color: var(--accent); }
        .calendar-day.today .calendar-header { background: rgba(56, 189, 248, 0.1); border-bottom-color: var(--accent); }
        
        .calendar-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            text-align: center;
            background: rgba(255,255,255,0.02);
            border-top-left-radius: var(--radius);
            border-top-right-radius: var(--radius);
        }
        .calendar-header .day-name { font-weight: 600; font-size: 1.1rem; color: var(--text-main); }
        .calendar-header .day-date { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.25rem; }
        
        .calendar-body {
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            flex: 1;
        }
        .empty-state { text-align: center; margin: auto; color: var(--text-muted); font-size: 0.875rem; font-style: italic; }
        
        .slot-item {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            transition: transform 0.2s, border-color 0.2s;
        }
        .slot-item:hover { transform: translateY(-2px); border-color: var(--accent); }
        .slot-time { font-size: 1.25rem; font-weight: 700; color: var(--accent); margin-bottom: 0.5rem; text-align: center; }
        
        .dosen-info { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
        .dosen-avatar { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; color: white; }
        
        .booking-form textarea {
            width: 100%; padding: 0.75rem; background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 6px; color: var(--text-main); font-size: 0.8125rem; resize: vertical;
            min-height: 60px; margin-bottom: 0.75rem;
        }
        .booking-form textarea:focus { outline: none; border-color: var(--accent); }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s; border: none; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #0f172a; }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
        .btn-sm { padding: 0.5rem; font-size: 0.8125rem; }

        .flash { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9375rem; }
        .flash-ok { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
        .flash-err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="/mahasiswa/dashboard.php" class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Booking.</span>
    </a>
    <nav class="sidebar-nav">
        <a href="/mahasiswa/dashboard.php" class="nav-item active">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Dashboard
        </a>
        <a href="/mahasiswa/riwayat.php" class="nav-item">
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
            <p style="color: var(--text-muted); font-size: 0.875rem;">Cari dosen dan ajukan konsultasi untuk 7 hari ke depan.</p>
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

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Pending</div>
            <div class="stat-value" style="color: var(--yellow)"><?= (int)$stats['pending'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Disetujui</div>
            <div class="stat-value" style="color: var(--green)"><?= (int)$stats['approved'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Ditolak</div>
            <div class="stat-value" style="color: var(--red)"><?= (int)$stats['ditolak'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Selesai</div>
            <div class="stat-value" style="color: var(--text-muted)"><?= (int)$stats['selesai'] ?></div>
        </div>
    </div>

    <form class="filter-section" method="GET">
        <div class="filter-group">
            <div style="position: relative; flex: 1; min-width: 200px; max-width: 300px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" name="search" placeholder="Cari nama dosen..." class="filter-input" value="<?= htmlspecialchars($search_dosen) ?>" style="padding-left: 2.5rem; width: 100%;">
            </div>
            <select name="dosen" class="filter-input">
                <option value="">Semua Dosen</option>
                <?php foreach ($dosens as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $filter_dosen == $d['id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nama']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter Kalender</button>
            <?php if ($filter_dosen || $search_dosen): ?>
                <a href="/mahasiswa/dashboard.php" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <h2 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1.5rem;">Kalender Konsultasi (7 Hari Ke Depan)</h2>
    
    <div class="calendar-scroll">
        <?php foreach ($calendar as $date => $day_slots): ?>
            <?php $is_today = ($date === date('Y-m-d')); ?>
            <div class="calendar-day <?= $is_today ? 'today' : '' ?>">
                <div class="calendar-header">
                    <div class="day-name"><?= $hari_indo[date('l', strtotime($date))] ?> <?= $is_today ? '(Hari Ini)' : '' ?></div>
                    <div class="day-date"><?= date('d M Y', strtotime($date)) ?></div>
                </div>
                <div class="calendar-body">
                    <?php if (empty($day_slots)): ?>
                        <div class="empty-state">Belum ada jadwal dosen tersedia.</div>
                    <?php else: ?>
                        <?php foreach ($day_slots as $slot): ?>
                            <div class="slot-item">
                                <div class="slot-time"><?= htmlspecialchars($slot['jam_mulai']) ?> - <?= htmlspecialchars($slot['jam_selesai']) ?></div>
                                <div class="dosen-info">
                                    <div class="dosen-avatar" style="background:<?= htmlspecialchars(get_avatar_color($slot['nama_dosen'])) ?>">
                                        <?= htmlspecialchars(get_initials($slot['nama_dosen'])) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($slot['nama_dosen']) ?></div>
                                        <?php if (!empty($slot['catatan_slot'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); font-style: italic; margin-top: 2px;">
                                                "<?= htmlspecialchars($slot['catatan_slot']) ?>"
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <form class="booking-form" method="POST" action="/mahasiswa/action_booking.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="jadwal_id" value="<?= (int)$slot['id'] ?>">
                                    <textarea name="topik" placeholder="Topik bimbingan..." required></textarea>
                                    <button type="submit" class="btn btn-primary btn-sm" style="width: 100%;">Ajukan Sekarang</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<script>
    document.querySelectorAll('.booking-form').forEach(form => {
        form.addEventListener('submit', function () {
            this.querySelector('button[type=submit]').disabled = true;
            this.querySelector('button[type=submit]').textContent = 'Memproses...';
        });
    });
</script>

</body>
</html>