<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/auth.php';

wiki_start_session();

$error = '';
$redirect = $_GET['redirect'] ?? 'index.php';
// Only allow local redirects.
if (!preg_match('#^[a-zA-Z0-9_./?=&%-]*$#', $redirect) || strpos($redirect, '//') === 0) {
    $redirect = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $postedRedirect = $_POST['redirect'] ?? $redirect;

    if (wiki_attempt_login($username, $password)) {
        header('Location: ' . ($postedRedirect !== '' ? $postedRedirect : 'index.php'));
        exit;
    }

    $error = 'Invalid username or password.';
    $redirect = $postedRedirect;
}

if (wiki_is_logged_in()) {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/views/login.php';
