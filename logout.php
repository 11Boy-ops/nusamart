<?php
declare(strict_types=1);
require_once __DIR__ . '/config/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_is_valid()) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    redirect('login.php?logged_out=1');
}

redirect('index.php');
