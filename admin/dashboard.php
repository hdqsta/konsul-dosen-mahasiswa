<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

$user = require_role('admin');
$pdo = get_pdo();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$flash = $_SESSION['flash'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);

// Analytics
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$users_count = ['mahasiswa' => 0, 'dosen' => 0, 'admin' => 0];
while ($row = $stmt->fetch()) {
    $users_count[$row['role']] = $row['count'];
}

$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM booking GROUP BY status");
$booking_count = ['pending' => 0, 'selesai' => 0];
while ($row = $stmt->fetch()) {
    if (isset($booking_count[$row['status']])) {
        $booking_count[$row['status']] = $row['count'];
    }
}

// Get filter
$filter_role = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT id, identifier, nama, role, is_active, created_at FROM users WHERE id != ?";
$params = [$user['user_id']];

if ($filter_role) {
    $sql .= " AND role = ?";
    $params[] = $filter_role;
}
if ($search) {
    $sql .= " AND (nama LIKE ? OR identifier LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users_list = $stmt->fetchAll();

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
    <title>Dashboard Admin - Sistem Booking Konsultasi</title>
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
        
        .main-content { flex: 1; padding: 2rem; max-width: 1200px; margin: 0 auto; width: 100%; overflow: hidden;}
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .user-profile { display: flex; align-items: center; gap: 1rem; }
        .avatar { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem; }
        .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem; text-align: center; }
        .stat-label { color: var(--text-muted); font-size: 0.8125rem; font-weight: 500; margin-bottom: 0.25rem; }
        .stat-value { font-size: 1.5rem; font-weight: 700; }

        .dashboard-layout { display: grid; grid-template-columns: 1fr 380px; gap: 2rem; }
        @media (max-width: 1024px) { .dashboard-layout { grid-template-columns: 1fr; } }
        
        .section-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.5rem; overflow: hidden; }
        .section-title { font-size: 1.125rem; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; color: var(--text-muted); }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); font-size: 0.9375rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--accent); }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s; border: none; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #0f172a; }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-main); }
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.75rem; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; color: var(--text-muted); font-size: 0.8125rem; font-weight: 600; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 1rem; border-bottom: 1px solid var(--border); font-size: 0.9375rem; }
        tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .badge-danger  { background: rgba(239, 68, 68, 0.1); color: #f87171; }
        .badge-dosen { background: rgba(56, 189, 248, 0.1); color: #38bdf8; }
        .badge-mahasiswa { background: rgba(139, 92, 246, 0.1); color: #a78bfa; }
        .badge-admin { background: rgba(245, 158, 11, 0.1); color: #fbbf24; }
        
        .flash { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9375rem; }
        .flash-ok { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
        .flash-err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }

        .filter-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-input { padding: 0.6rem 0.75rem; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main); font-size: 0.875rem; }
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="/admin/dashboard.php" class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Booking.</span>
    </a>
    <nav class="sidebar-nav">
        <a href="/admin/dashboard.php" class="nav-item active">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Dashboard Admin
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
            <p style="color: var(--text-muted); font-size: 0.875rem;">Kelola pengguna dan awasi aktivitas sistem.</p>
        </div>
        <div class="user-profile">
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
            <div class="stat-label">Total Dosen</div>
            <div class="stat-value" style="color: var(--accent)"><?= (int)$users_count['dosen'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Mahasiswa</div>
            <div class="stat-value" style="color: var(--purple, #a78bfa)"><?= (int)$users_count['mahasiswa'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Konsultasi Selesai</div>
            <div class="stat-value" style="color: var(--green)"><?= (int)$booking_count['selesai'] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Konsultasi Pending</div>
            <div class="stat-value" style="color: var(--yellow)"><?= (int)$booking_count['pending'] ?></div>
        </div>
    </div>

    <div class="dashboard-layout">
        <div>
            <section class="section-card">
                <div class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Manajemen Pengguna
                </div>

                <form class="filter-bar" method="GET">
                    <input type="text" name="search" placeholder="Cari nama atau NIP/NIM..." class="filter-input" value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">
                    <select name="role" class="filter-input">
                        <option value="">Semua Role</option>
                        <option value="dosen" <?= $filter_role === 'dosen' ? 'selected' : '' ?>>Dosen</option>
                        <option value="mahasiswa" <?= $filter_role === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
                        <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <?php if ($filter_role || $search): ?>
                        <a href="/admin/dashboard.php" class="btn btn-outline btn-sm">Reset</a>
                    <?php endif; ?>
                </form>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama & Identifier</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users_list)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted);">Pengguna tidak ditemukan.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users_list as $u): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($u['nama']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($u['identifier']) ?></div>
                                    </td>
                                    <td>
                                        <?php 
                                        $rc = 'badge-dosen';
                                        if ($u['role'] === 'mahasiswa') $rc = 'badge-mahasiswa';
                                        if ($u['role'] === 'admin') $rc = 'badge-admin';
                                        ?>
                                        <span class="badge <?= $rc ?>"><?= ucfirst($u['role']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['is_active']): ?>
                                            <span class="badge badge-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="/admin/action_user.php" style="display:inline-block;" onsubmit="return confirm('Ubah status pengguna ini?')">
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <?php if ($u['is_active']): ?>
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--red); border-color: rgba(239, 68, 68, 0.2)">Nonaktifkan</button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--green); border-color: rgba(16, 185, 129, 0.2)">Aktifkan</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div>
            <section class="section-card">
                <div class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                    Tambah Pengguna
                </div>
                <form method="POST" action="/admin/action_user.php">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    
                    <div class="form-group">
                        <label>NIM / NIP / Identifier</label>
                        <input type="text" name="identifier" required placeholder="Contoh: 123456789">
                    </div>
                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama" required placeholder="Nama Lengkap">
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="dosen">Dosen</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="text" name="password" placeholder="Minimal 6 karakter (Opsional)">
                    </div>
                    
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem;">
                        * Jika password dikosongkan, default-nya adalah <strong>password123</strong>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%">Tambahkan Pengguna</button>
                </form>
            </section>
        </div>
    </div>
</main>

</body>
</html>
