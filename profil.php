<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/notifikasi.php';

init_session();

// Redirect if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$user = [
    'user_id' => $_SESSION['user_id'],
    'nama'    => $_SESSION['nama'] ?? '',
    'role'    => $_SESSION['role'] ?? '',
];

$pdo = get_pdo();

// Get user data from database
$stmt = $pdo->prepare("SELECT identifier, nama, role, bio, departemen FROM users WHERE id = ?");
$stmt->execute([$user['user_id']]);
$user_data = $stmt->fetch();

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Flash
$flash = $_SESSION['flash'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash'], $_SESSION['flash_error']);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403); exit('403 Forbidden.');
    }

    $new_nama = trim($_POST['nama'] ?? '');
    $new_departemen = trim($_POST['departemen'] ?? '');
    $new_bio = trim($_POST['bio'] ?? '');

    if (empty($new_nama)) {
        $_SESSION['flash_error'] = 'Nama tidak boleh kosong.';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET nama = ?, departemen = ?, bio = ? WHERE id = ?");
        if ($stmt->execute([$new_nama, $new_departemen, $new_bio, $user['user_id']])) {
            $_SESSION['nama'] = $new_nama;
            $_SESSION['flash'] = 'Profil berhasil diperbarui.';
        } else {
            $_SESSION['flash_error'] = 'Gagal memperbarui profil.';
        }
    }
    header('Location: /profil.php');
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('403 Forbidden.');
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['flash_error'] = 'Semua field harus diisi.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['flash_error'] = 'Password baru dan konfirmasi tidak cocok.';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['flash_error'] = 'Password minimal 6 karakter.';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user['user_id']]);
        $stored_hash = $stmt->fetchColumn();

        if (password_verify($current_password, $stored_hash)) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_hash, $user['user_id']]);

            // Dummy bcrypt untuk timing attack protection
            password_verify('dummy', $stored_hash);

            $_SESSION['flash'] = 'Password berhasil diubah.';
        } else {
            // Dummy bcrypt
            password_verify($current_password, $stored_hash);
            $_SESSION['flash_error'] = 'Password saat ini salah.';
        }
    }

    header('Location: /profil.php');
    exit();
}

// Notification
$notif_count  = count_unread_notifikasi($user['user_id']);
$notif_list   = get_notifikasi($user['user_id']);
$mark_all_url = $user['role'] === 'dosen'
    ? '/dosen/action_baca_notif.php'
    : '/mahasiswa/action_baca_notif.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Sistem Booking Konsultasi</title>
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
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 2rem;
            color: white;
            background: #4F46E5;
            margin-bottom: 1.5rem;
        }

        .section-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* FORMS */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .form-group input {
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
            display: inline-flex; align-items: center; justify-content: center;
            gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px;
            font-weight: 600; font-size: 0.875rem; cursor: pointer;
            transition: all 0.2s; border: none;
        }
        .btn-primary { background: var(--accent); color: #0f172a; }

        /* INFO LIST */
        .info-list { display: flex; flex-direction: column; gap: 1rem; }
        .info-item { display: flex; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .info-item:last-child { border-bottom: none; }
        .info-label { color: var(--text-muted); font-size: 0.875rem; }
        .info-value { font-weight: 600; color: var(--text-main); }

        /* FLASH */
        .flash { padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9375rem; }
        .flash-ok { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; }
        .flash-err { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; }
    </style>
</head>
<body>

<aside class="sidebar">
    <a href="/" class="sidebar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span>Booking.</span>
    </a>
    <nav class="sidebar-nav">
        <?php if ($user['role'] === 'admin'): ?>
            <a href="/admin/dashboard.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard Admin
            </a>
        <?php elseif ($user['role'] === 'mahasiswa'): ?>
            <a href="/mahasiswa/dashboard.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard
            </a>
            <a href="/mahasiswa/riwayat.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Riwayat
            </a>
        <?php else: ?>
            <a href="/dosen/dashboard.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Dashboard
            </a>
            <a href="/dosen/riwayat.php" class="nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Riwayat
            </a>
        <?php endif; ?>
        <a href="/profil.php" class="nav-item active">
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
            <h1 style="font-size: 1.5rem; font-weight: 700;">Profil Saya</h1>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Kelola informasi akun dan kata sandi Anda.</p>
        </div>
        <div class="user-profile">
            <?php include __DIR__ . '/includes/notif_bell.php'; ?>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash flash-ok">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <?= htmlspecialchars($flash) ?>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="flash flash-err">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" x2="9" y1="9" y2="15"/><line x1="9" x2="15" y1="9" y2="15"/></svg>
            <?= htmlspecialchars($flash_error) ?>
        </div>
    <?php endif; ?>

    <section class="section-card">
        <div class="avatar-large">
            <?= strtoupper($user['nama'][0] ?? 'U') ?>
        </div>
        <div class="section-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Edit Informasi Profil
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" value="<?= htmlspecialchars($user_data['nama']) ?>" required>
            </div>
            
            <div class="form-group">
                <label><?= $user_data['role'] === 'mahasiswa' ? 'Program Studi' : 'Departemen / Bidang' ?></label>
                <input type="text" name="departemen" value="<?= htmlspecialchars($user_data['departemen'] ?? '') ?>" placeholder="Contoh: Teknik Informatika">
            </div>

            <div class="form-group">
                <label>Bio / Catatan Singkat</label>
                <textarea name="bio" style="width:100%; padding:0.75rem 1rem; background:var(--bg-input); border:1px solid var(--border); border-radius:8px; color:var(--text-main); font-size:0.9375rem; min-height:80px; font-family:inherit; resize:vertical;" placeholder="Tuliskan sedikit tentang diri Anda..."><?= htmlspecialchars($user_data['bio'] ?? '') ?></textarea>
            </div>

            <div style="margin-bottom: 2rem; border-top: 1px solid var(--border); padding-top: 1.5rem;">
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label"><?= $user_data['role'] === 'mahasiswa' ? 'NIM' : 'NIP' ?></span>
                        <span class="info-value"><?= htmlspecialchars($user_data['identifier']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status Akun</span>
                        <span class="info-value" style="color: var(--green)">Aktif</span>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </section>

    <section class="section-card">
        <div class="section-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Keamanan & Kata Sandi
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-group">
                <label>Kata Sandi Saat Ini</label>
                <input type="password" name="current_password" required placeholder="Masukkan kata sandi lama">
            </div>

            <div class="form-group">
                <label>Kata Sandi Baru</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter">
            </div>

            <div class="form-group">
                <label>Konfirmasi Kata Sandi Baru</label>
                <input type="password" name="confirm_password" required minlength="6" placeholder="Ulangi kata sandi baru">
            </div>

            <button type="submit" class="btn btn-primary">Perbarui Kata Sandi</button>
        </form>
    </section>
</main>

</body>
</html>