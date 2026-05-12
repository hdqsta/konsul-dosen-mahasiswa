<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

init_session();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        $redirect = '/admin/dashboard.php';
    } else {
        $redirect = $_SESSION['role'] === 'dosen'
            ? '/dosen/dashboard.php'
            : '/mahasiswa/dashboard.php';
    }
    header("Location: $redirect");
    exit();
}

$error = '';

// -----------------------------------------------------------
// HELPER:ambil IP real dengan whitelist proxy
// -----------------------------------------------------------
function get_client_ip(): string {
    // Whitelist IP proxy internal — adjust according to infrastructure
    $proxy_whitelist = ['127.0.0.1', '::1'];

    $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';

    // Jika dari proxy internal, percayai header forwarded
    if (in_array($remote_addr, $proxy_whitelist, true)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }

    // Default: hanya REMOTE_ADDR
    if (filter_var($remote_addr, FILTER_VALIDATE_IP)) {
        return $remote_addr;
    }

    return '0.0.0.0';
}

// -----------------------------------------------------------
// RATE LIMITING — berbasis database (IP + identifier)
// Window: 15 menit, max: 5 percobaan
// -----------------------------------------------------------
function is_rate_limited(PDO $pdo, string $ip, string $identifier): bool {
    $window = date('Y-m-d H:i:s', strtotime('-15 minutes'));

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM login_attempts
        WHERE (ip_address = :ip OR identifier = :identifier)
          AND attempted_at >= :window
    ");
    $stmt->execute([
        'ip'         => $ip,
        'identifier' => $identifier,
        'window'     => $window,
    ]);

    return (int)$stmt->fetch()['total'] >= 5;
}

function record_attempt(PDO $pdo, string $ip, string $identifier): void {
    $pdo->prepare("
        INSERT INTO login_attempts (ip_address, identifier)
        VALUES (:ip, :identifier)
    ")->execute(['ip' => $ip, 'identifier' => $identifier]);
}

function clear_attempts(PDO $pdo, string $ip, string $identifier): void {
    $pdo->prepare("
        DELETE FROM login_attempts
        WHERE ip_address = :ip OR identifier = :identifier
    ")->execute(['ip' => $ip, 'identifier' => $identifier]);
}

// -----------------------------------------------------------
// PROSES LOGIN
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_pdo();
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $ip         = get_client_ip();

    if (empty($identifier) || empty($password)) {
        $error = 'Identifier dan password wajib diisi.';

    } elseif (is_rate_limited($pdo, $ip, $identifier)) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi dalam 15 menit.';

    } else {
        $stmt = $pdo->prepare("
            SELECT id, nama, role, password, is_active
            FROM users
            WHERE identifier = :identifier
            LIMIT 1
        ");
        $stmt->execute(['identifier' => $identifier]);
        $user = $stmt->fetch();

        // ---------------------------------------------------
        // PATCH TIMING ATTACK:
        // Cost factor dummy WAJIB identik dengan cost
        // yang digunakan saat INSERT user ke database ($12$)
        // ---------------------------------------------------
        $dummy_hash = '$2y$12$invaliddummyhashfortiming.neutrality00000000000000000u';

        $hash_to_verify = ($user && $user['is_active'])
            ? $user['password']
            : $dummy_hash;

        $password_valid = password_verify($password, $hash_to_verify);

        if ($user && $user['is_active'] && $password_valid) {
            clear_attempts($pdo, $ip, $identifier);
            login_user($user);
            if ($user['role'] === 'admin') {
                $redirect = '/admin/dashboard.php';
            } else {
                $redirect = $user['role'] === 'dosen'
                    ? '/dosen/dashboard.php'
                    : '/mahasiswa/dashboard.php';
            }
            header("Location: $redirect");
            exit();

        } elseif ($user && !$user['is_active']) {
            $error = 'Akun nonaktif. Hubungi administrator.';

        } else {
            record_attempt($pdo, $ip, $identifier);
            $error = 'Kredensial tidak valid.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Booking Konsultasi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg-base   : #0f172a;
            --bg-card   : #1e293b;
            --bg-input  : #0f172a;
            --border    : #334155;
            --text-main : #f1f5f9;
            --text-muted: #94a3b8;
            --accent    : #38bdf8;
            --accent-hover : #0ea5e9;
            --error     : #ef4444;
            --radius    : 12px;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-base);
            background-image: radial-gradient(circle at top right, rgba(56, 189, 248, 0.1), transparent),
                              radial-gradient(circle at bottom left, rgba(56, 189, 248, 0.05), transparent);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-container {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-logo {
            margin-bottom: 1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
        }
        .brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.025em;
        }
        .brand-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            margin-bottom: 2rem;
        }
        .login-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-size: 0.875rem; 
            font-weight: 500;
            color: var(--text-main); 
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-group input:focus { 
            outline: none; 
            border-color: var(--accent); 
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 3rem; }
        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .toggle-password:hover { color: var(--text-main); }
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: var(--accent);
            color: #0f172a;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        .btn-login:hover { 
            background: var(--accent-hover);
            transform: translateY(-1px);
        }
        .btn-login:active {
            transform: translateY(0);
        }
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .error-msg svg {
            flex-shrink: 0;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-muted);
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand-section">
            <div class="brand-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
            </div>
            <div class="brand-name">Booking Konsultasi</div>
            <div class="brand-subtitle">Sistem Manajemen Pertemuan Dosen & Mahasiswa</div>
        </div>

        <div class="login-card">
            <div class="login-header">
                <h1>Selamat Datang</h1>
                <p>Silakan masuk ke akun Anda untuk melanjutkan.</p>
            </div>

            <?php if ($error): ?>
                <div class="error-msg">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 15A7 7 0 108 1a7 7 0 000 14zM8 4a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 018 4zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="identifier">NIM / NIP</label>
                    <input type="text" name="identifier" id="identifier" placeholder="Masukkan NIM atau NIP" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password" placeholder="••••••••" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()" title="Lihat Password">
                            <span id="toggle-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login">Masuk ke Sistem</button>
            </form>
        </div>

        <div class="footer">
            &copy; <?= date('Y') ?> Sistem Booking Konsultasi. All rights reserved.
        </div>
    </div>

    <script>
        function togglePassword() {
            var input = document.getElementById('password');
            var iconContainer = document.getElementById('toggle-icon');
            
            const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>`;
            const eyeOffIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>`;

            if (input.type === 'password') {
                input.type = 'text';
                iconContainer.innerHTML = eyeOffIcon;
            } else {
                input.type = 'password';
                iconContainer.innerHTML = eyeIcon;
            }
        }
    </script>
</body>
</html>