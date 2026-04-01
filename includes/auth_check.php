<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . get_auth_base() . '/auth/login.php');
        exit;
    }
}

function require_role(...$roles) {
    require_login();
    if (!in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        die('<h2 style="font-family:sans-serif;padding:40px;color:#f87171">403 — Access Denied</h2>');
    }
}

function get_auth_base() {
    $depth = substr_count($_SERVER['PHP_SELF'], '/') - 2;
    return str_repeat('../', max($depth, 0));
}

function is_role(...$roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}
