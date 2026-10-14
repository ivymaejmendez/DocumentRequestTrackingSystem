<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/auth.php';
if (!empty($_SESSION['user'])) {
    header('Location: /document-request-system/');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u['password'])) {
        unset($u['password']);
        $_SESSION['user'] = $u;
        header('Location: /document-request-system/');
        exit;
    }
    $error = 'Invalid email or password.';
}
$title='Login';
include __DIR__.'/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-5">
<div class="card p-4">
<h3>Login</h3>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post">
<div class="mb-3"><label>Email</label><input class="form-control" type="email" name="email" required></div>
<div class="mb-3"><label>Password</label><input class="form-control" type="password" name="password" required></div>
<button class="btn btn-primary w-100">Login</button>
</form>
<hr>
</div></div></div>
<?php include __DIR__.'/../includes/footer.php'; ?>
