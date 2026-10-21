<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/auth.php';
if (!empty($_SESSION['user'])) {
    header('Location: ' . app_url(($_SESSION['user']['role'] === 'admin')
        ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}
$title = 'Document Request Tracking System';
include __DIR__.'/includes/header.php';
?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card p-5 text-center">
<h1 class="mb-3">Document Request Tracking System</h1>
<p class="lead">Submit, monitor, and manage document requests online.</p>
<a class="btn btn-primary btn-lg" href="<?= app_url('auth/login.php') ?>">Login</a>
</div></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
