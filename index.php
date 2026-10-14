<?php
require_once __DIR__.'/config/database.php';
require_once __DIR__.'/includes/auth.php';
if (!empty($_SESSION['user'])) {
    header('Location: '.($_SESSION['user']['role']==='admin' ? '/document-request-system/admin/dashboard.php' : '/document-request-system/user/dashboard.php'));
    exit;
}
$title='Document Request Tracking System';
include __DIR__.'/includes/header.php';
?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card p-5 text-center">
<h1 class="mb-3">Document Request Tracking System</h1>
<p class="lead">Submit, monitor, and manage document requests online.</p>
<a class="btn btn-primary btn-lg" href="/document-request-system/auth/login.php">Login</a>
</div></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
