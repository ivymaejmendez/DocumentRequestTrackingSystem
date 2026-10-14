<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$title = $title ?? 'Document Request System';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/document-request-system/assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bg-dark navbar-dark">
<div class="container">
<a class="navbar-brand fw-bold" href="/document-request-system/">Document Requests</a>
<div>
<?php if (!empty($_SESSION['user'])): ?>
<span class="text-white me-3"><?= htmlspecialchars($_SESSION['user']['name']) ?></span>
<a class="btn btn-outline-light btn-sm" href="/document-request-system/auth/logout.php">Logout</a>
<?php endif; ?>
</div>
</div>
</nav>
<main class="container py-4">
