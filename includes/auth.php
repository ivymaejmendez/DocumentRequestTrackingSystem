<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Detects the app's base URL path at runtime.
 * Works whether the app is at:
 *   - / (root, e.g. InfinityFree: trackingsystem.infinityfree.io/)
 *   - /document-request-system/ (local XAMPP subfolder)
 */
function app_base(): string {
    static $base = null;
    if ($base !== null) return $base;

    // Walk up from the current script to find the app root
    $scriptDir  = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $appRoot    = realpath(dirname(__DIR__));      // …/document-request-system
    $docRoot    = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

    if ($docRoot && $appRoot && $scriptDir) {
        if (str_starts_with($appRoot, $docRoot)) {
            $rel = substr($appRoot, strlen($docRoot));
            $base = '/' . trim(str_replace('\\', '/', $rel), '/');
            $base = $base === '/' ? '' : $base;
            return $base;
        }
    }

    // Fallback: derive from SCRIPT_NAME (always available)
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // e.g. /document-request-system/auth/login.php  →  /document-request-system
    //      /auth/login.php                           →  (empty)
    $parts = explode('/', trim($script, '/'));
    // The app has known sub-dirs; if the 1st segment is one of them, base = ''
    $knownDirs = ['auth','admin','user','includes','config','assets','tests'];
    if (!empty($parts[0]) && !in_array($parts[0], $knownDirs, true)) {
        $base = '/' . $parts[0];
    } else {
        $base = '';
    }

    return $base;
}

function app_url(string $path = ''): string {
    return app_base() . '/' . ltrim($path, '/');
}

function require_login(): void {
    if (empty($_SESSION['user'])) {
        header('Location: ' . app_url('auth/login.php'));
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied.');
    }
}
