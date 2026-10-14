<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_login();
$stmt=$pdo->prepare("SELECT r.*, d.name document_name FROM document_requests r JOIN document_types d ON d.id=r.document_type_id WHERE r.user_id=? ORDER BY r.requested_at DESC");
$stmt->execute([$_SESSION['user']['id']]); $rows=$stmt->fetchAll();
$title='My Requests'; include __DIR__.'/../includes/header.php';
?>
<h2>My Requests</h2>
<div class="card p-3"><div class="table-responsive"><table class="table">
<thead><tr><th>Reference</th><th>Document</th><th>Qty</th><th>Status</th><th>Requested</th></tr></thead>
<tbody><?php foreach($rows as $r): ?><tr>
<td><?= htmlspecialchars($r['reference_no']) ?></td><td><?= htmlspecialchars($r['document_name']) ?></td><td><?= $r['quantity'] ?></td>
<td><span class="badge text-bg-primary"><?= htmlspecialchars($r['status']) ?></span></td><td><?= htmlspecialchars($r['requested_at']) ?></td>
</tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
