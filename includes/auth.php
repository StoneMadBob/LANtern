<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

/**
 * Require a signed-in user
 */
function require_login() {
    if (!is_logged_in()) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
        header('Location: /login.php?redirect=' . urlencode($redirect));
        exit;
    }
}

/**
 * Check whether a redirect target is a safe path on this site
 */
function is_safe_redirect_target($target) {
    return is_string($target)
        && str_starts_with($target, '/')
        && !str_starts_with($target, '//')
        && !str_contains($target, '\\')
        && !preg_match('/[\x00-\x1F\x7F]/', $target);
}

/**
 * Require one of the supplied roles
 */
function require_roles(array $roles) {
    if (!is_logged_in()) {
        require_login();
    }

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
        header('Location: /dashboard.php');
        exit;
    }
}

/**
 * Require administrator role
 */
function require_admin() {
    require_roles(['admin']);
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log out user
 */
function logout() {
    session_unset();
    session_destroy();
    header("Location: /login.php");
    exit;
}
