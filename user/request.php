<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
require_login();
if ($_SESSION['user']['role']==='admin') { header('Location:/document-request-system/admin/dashboard.php'); exit; }
$types=$pdo->query("SELECT * FROM document_types ORDER BY name")->fetchAll();
$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $type=(int)$_POST['document_type_id']; $purpose=trim($_POST['purpose']); $qty=max(1,(int)$_POST['quantity']);
    $ref='DRS-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    $stmt=$pdo->prepare("INSERT INTO document_requests(reference_no,user_id,document_type_id,purpose,quantity,status,requested_at,updated_at) VALUES(?,?,?,?,?,'Pending',NOW(),NOW())");
    $stmt->execute([$ref,$_SESSION['user']['id'],$type,$purpose,$qty]);
    $id=$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO request_status_history(request_id,status,remarks,changed_by) VALUES(?,?,?,?)")->execute([$id,'Pending','Request submitted',$_SESSION['user']['id']]);
    $message="Request submitted successfully. Reference No: $ref";
}
$title='New Request'; include __DIR__.'/../includes/header.php';
?>
<h2>New Document Request</h2>
<?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="card p-4">
<form method="post">
<div class="mb-3"><label>Document Type</label><select name="document_type_id" class="form-select" required>
<option value="">Select document</option><?php foreach($types as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
</select></div>
<div class="mb-3"><label>Purpose</label><textarea name="purpose" class="form-control" required></textarea></div>
<div class="mb-3"><label>Quantity</label><input type="number" min="1" name="quantity" value="1" class="form-control" required></div>
<button class="btn btn-primary">Submit Request</button>
<a class="btn btn-secondary" href="dashboard.php">Cancel</a>
</form></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
