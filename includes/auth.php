<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: /document-request-system/auth/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if ($_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied.');
    }
}
