<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$title = $title ?? 'Document Request System';

$base    = '/document-request-system';
$user    = $_SESSION['user'] ?? null;
$isAdmin = $user && ($user['role'] ?? '') === 'admin';

/* Which page are we on? e.g. "admin/requests.php" */
$here = basename(dirname($_SERVER['SCRIPT_NAME'])) . '/' . basename($_SERVER['SCRIPT_NAME']);

$menu = $isAdmin
    ? [
        ['admin/dashboard.php', "$base/admin/dashboard.php", 'bi-speedometer2', 'Dashboard'],
        ['admin/requests.php',  "$base/admin/requests.php",  'bi-folder2-open', 'Requests'],
        ['admin/residents.php', "$base/admin/residents.php", 'bi-people',       'Residents'],
      ]
    : [
        ['user/dashboard.php',   "$base/user/dashboard.php",   'bi-house',        'Dashboard'],
        ['user/request.php',     "$base/user/request.php",     'bi-plus-circle',  'Request a document'],
        ['user/my_requests.php', "$base/user/my_requests.php", 'bi-list-check',   'My requests'],
      ];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
<link href="<?= $base ?>/assets/css/style.css" rel="stylesheet">
<style>
.site-nav { background:#0b2a5b; border-bottom:6px solid #f2b705; font-family:'Source Sans 3',system-ui,sans-serif; }
.site-nav .navbar-brand { font-family:'Bricolage Grotesque',system-ui,sans-serif; font-weight:800; color:#fff; letter-spacing:-.01em; }
.site-nav .nav-link { color:#d5ddee; font-weight:600; padding:.55rem .9rem; border-radius:6px; }
.site-nav .nav-link:hover { color:#fff; background:rgba(255,255,255,.08); }
.site-nav .nav-link.active { color:#fff; box-shadow:inset 0 -3px 0 #f2b705; border-radius:6px 6px 0 0; }
.site-nav .nav-link:focus-visible, .site-nav .navbar-toggler:focus-visible, .site-nav .btn:focus-visible { outline:3px solid #f2b705; outline-offset:2px; }
.site-nav .navbar-toggler { border-color:rgba(255,255,255,.4); }
.site-nav .who { color:#fff; font-weight:600; }
.site-nav .who small { display:block; color:#b8c4dc; font-weight:400; line-height:1.1; }
.site-nav .btn-logout { color:#0b2a5b; background:#f2b705; border:0; font-weight:700; }
.site-nav .btn-logout:hover { background:#ffc928; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark site-nav">
<div class="container">
    <a class="navbar-brand" href="<?= $base ?>/">Barangay Document Requests</a>

    <?php if ($user): ?>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteMenu"
            aria-controls="siteMenu" aria-expanded="false" aria-label="Toggle menu">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="siteMenu">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
            <?php foreach ($menu as [$key, $href, $icon, $label]): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $here === $key ? 'active' : '' ?>" href="<?= $href ?>"
                       <?= $here === $key ? 'aria-current="page"' : '' ?>>
                        <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="d-flex align-items-center gap-3">
            <span class="who">
                <?= htmlspecialchars($user['name'] ?? 'Account') ?>
                <small><?= $isAdmin ? 'Administrator' : 'Resident' ?></small>
            </span>
            <a class="btn btn-logout btn-sm" href="<?= $base ?>/auth/logout.php">Logout</a>
        </div>
    </div>
    <?php endif; ?>
</div>
</nav>
<main class="container py-4">