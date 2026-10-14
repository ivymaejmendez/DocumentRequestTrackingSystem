<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_login();
if ($_SESSION['user']['role'] === 'admin') { header('Location:/document-request-system/admin/dashboard.php'); exit; }
$stmt=$pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE user_id=?"); $stmt->execute([$_SESSION['user']['id']]); $total=$stmt->fetchColumn();
$stmt=$pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE user_id=? AND status='Completed'"); $stmt->execute([$_SESSION['user']['id']]); $completed=$stmt->fetchColumn();
$title='My Dashboard'; include __DIR__.'/../includes/header.php';
?>
<h2>My Dashboard</h2>
<div class="row g-3 my-2">
<div class="col-md-6"><div class="card p-4"><div>Total Requests</div><div class="stat"><?= $total ?></div></div></div>
<div class="col-md-6"><div class="card p-4"><div>Completed</div><div class="stat"><?= $completed ?></div></div></div>
</div>
<a class="btn btn-primary" href="request.php">New Document Request</a>
<a class="btn btn-outline-secondary" href="my_requests.php">Track My Requests</a>
<?php include __DIR__.'/../includes/footer.php'; ?>
