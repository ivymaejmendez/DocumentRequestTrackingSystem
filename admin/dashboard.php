<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_admin();
$total=$pdo->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$pending=$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status='Pending'")->fetchColumn();
$processing=$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status='Processing'")->fetchColumn();
$completed=$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status='Completed'")->fetchColumn();
$title='Admin Dashboard'; include __DIR__.'/../includes/header.php';
?>
<h2>Admin Dashboard</h2>
<div class="row g-3">
<?php foreach([['Total',$total],['Pending',$pending],['Processing',$processing],['Completed',$completed]] as $s): ?>
<div class="col-md-3"><div class="card p-4"><div><?= $s[0] ?></div><div class="stat"><?= $s[1] ?></div></div></div>
<?php endforeach; ?>
</div><div class="mt-4"><a class="btn btn-primary" href="requests.php">Manage Requests</a></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
