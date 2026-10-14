<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_admin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id=(int)$_POST['id']; $status=$_POST['status']; $remarks=trim($_POST['remarks'] ?? '');
    $allowed=['Pending','Processing','Ready for Pickup','Completed','Rejected'];
    if(in_array($status,$allowed,true)) {
        $pdo->prepare("UPDATE document_requests SET status=?,remarks=?,updated_at=NOW(),completed_at=IF(?='Completed',NOW(),completed_at) WHERE id=?")->execute([$status,$remarks,$status,$id]);
        $pdo->prepare("INSERT INTO request_status_history(request_id,status,remarks,changed_by) VALUES(?,?,?,?)")->execute([$id,$status,$remarks,$_SESSION['user']['id']]);
    }
}
$rows=$pdo->query("SELECT r.*, u.name requester, d.name document_name FROM document_requests r JOIN users u ON u.id=r.user_id JOIN document_types d ON d.id=r.document_type_id ORDER BY r.requested_at DESC")->fetchAll();
$title='Manage Requests'; include __DIR__.'/../includes/header.php';
?>
<h2>Manage Requests</h2>
<div class="card p-3"><div class="table-responsive"><table class="table">
<thead><tr><th>Reference</th><th>Requester</th><th>Document</th><th>Status</th><th>Action</th></tr></thead>
<tbody><?php foreach($rows as $r): ?><tr>
<td><?= htmlspecialchars($r['reference_no']) ?></td><td><?= htmlspecialchars($r['requester']) ?></td><td><?= htmlspecialchars($r['document_name']) ?></td>
<td><?= htmlspecialchars($r['status']) ?></td><td>
<form method="post" class="d-flex gap-2">
<input type="hidden" name="id" value="<?= $r['id'] ?>">
<select name="status" class="form-select form-select-sm">
<?php foreach(['Pending','Processing','Ready for Pickup','Completed','Rejected'] as $s): ?><option <?= $s===$r['status']?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
</select><input name="remarks" class="form-control form-control-sm" placeholder="Remarks"><button class="btn btn-sm btn-primary">Update</button>
</form></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
