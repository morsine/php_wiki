<?php

function wiki_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(WIKI_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => WIKI_SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true, // enable once served over HTTPS
        ]);
        session_start();
    }
}

function wiki_is_logged_in(): bool
{
    wiki_start_session();

    if (empty($_SESSION['wiki_user']) || empty($_SESSION['wiki_login_time'])) {
        return false;
    }

    if (time() - $_SESSION['wiki_login_time'] > WIKI_SESSION_LIFETIME) {
        wiki_logout();
        return false;
    }

    return true;
}

function wiki_require_login(): void
{
    if (!wiki_is_logged_in()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: login.php?redirect=' . $redirect);
        exit;
    }
}

function wiki_attempt_login(string $username, string $password): bool
{
    $users = WIKI_USERS;

    if (!isset($users[$username])) {
        // Still hash to keep timing roughly consistent whether or not the user exists.
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuu');
        return false;
    }

    if (password_verify($password, $users[$username])) {
        wiki_start_session();
        session_regenerate_id(true);
        $_SESSION['wiki_user'] = $username;
        $_SESSION['wiki_login_time'] = time();
        return true;
    }

    return false;
}

function wiki_logout(): void
{
    wiki_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function wiki_current_user(): ?string
{
    wiki_start_session();
    return $_SESSION['wiki_user'] ?? null;
}
