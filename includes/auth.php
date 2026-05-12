<?php
// Session & authentication functions

function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Security headers
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}

function require_role(string $role): ?array {
    init_session();

    if (empty($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
        header('Location: /login.php');
        exit();
    }

    return [
        'user_id' => $_SESSION['user_id'],
        'nama'    => $_SESSION['nama']    ?? '',
        'role'    => $_SESSION['role']    ?? '',
    ];
}

function login_user(array $user): void {
    init_session();

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['nama']    = $user['nama'];
    $_SESSION['role']    = $user['role'];
}

function logout_user(): void {
    init_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: /login.php');
    exit();
}