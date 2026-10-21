<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* Already signed in? Go to the right dashboard. */
if (!empty($_SESSION['user'])) {
    header('Location: ' . app_url(($_SESSION['user']['role'] ?? '') === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}
if (empty($_SESSION['csrf_reg'])) $_SESSION['csrf_reg'] = bin2hex(random_bytes(16));

/* ---------- Detect the real columns of `users` ---------- */
$info = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
$uCols = array_column($info, 'Field');
$byField = array_column($info, null, 'Field');
$pick = function (array $want) use ($uCols) {
    foreach ($want as $w) if (in_array($w, $uCols, true)) return $w;
    return null;
};
$nameCol  = $pick(['name','full_name','fullname']);
$emailCol = $pick(['email','email_address']);
$passCol  = $pick(['password','password_hash','pass']);
$phoneCol = $pick(['contact_number','contact_no','contact','phone','phone_number','mobile']);
$addrCol  = $pick(['address','street']);
$dateCol  = $pick(['created_at','registered_at','date_registered','date_created']);
$missing  = !$nameCol || !$emailCol || !$passCol;

/* Role value for residents: use what the column allows, never 'admin' */
$residentRole = 'user';
if (in_array('role', $uCols, true) && preg_match('/^enum\((.*)\)$/i', $byField['role']['Type'], $m)) {
    $opts = array_map(fn($v) => trim($v, "'"), explode(',', $m[1]));
    foreach (['user', 'resident', 'citizen', 'member'] as $p) if (in_array($p, $opts, true)) { $residentRole = $p; break; }
    if (!in_array($residentRole, $opts, true)) {
        $nonAdmin = array_values(array_diff($opts, ['admin']));
        $residentRole = $nonAdmin[0] ?? 'user';
    }
}

/* ---------- Submit ---------- */
$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];
if (!$missing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'    => trim($_POST['name'] ?? ''),
        'email'   => strtolower(trim($_POST['email'] ?? '')),
        'phone'   => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
    ];
    $pass = $_POST['password'] ?? '';

    if (!hash_equals($_SESSION['csrf_reg'], $_POST['csrf'] ?? '')) $errors[] = 'Your session expired. Reload the page and try again.';
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) $errors['name'] = 'Enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if ($phoneCol && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['phone'])) $errors['phone'] = 'Enter a valid contact number, for example 0917 123 4567.';
    if ($addrCol && ($old['address'] === '' || mb_strlen($old['address']) > 255)) $errors['address'] = 'Enter your complete address in the barangay.';
    if (strlen($pass) < 8) $errors['password'] = 'Use at least 8 characters.';
    elseif ($pass !== ($_POST['password2'] ?? '')) $errors['password2'] = 'The two passwords do not match.';

    if (!$errors) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE `$emailCol` = ?");
        $dup->execute([$old['email']]);
        if ($dup->fetchColumn() > 0) $errors['email'] = 'An account with this email already exists. Try logging in instead.';
    }

    if (!$errors) {
        $cols = [$nameCol, $emailCol, $passCol]; $vals = [$old['name'], $old['email'], password_hash($pass, PASSWORD_DEFAULT)];
        if (in_array('role', $uCols, true)) { $cols[] = 'role'; $vals[] = $residentRole; }
        if ($phoneCol) { $cols[] = $phoneCol; $vals[] = $old['phone']; }
        if ($addrCol)  { $cols[] = $addrCol;  $vals[] = $old['address']; }
        if ($dateCol && $byField[$dateCol]['Default'] === null) { $cols[] = $dateCol; $vals[] = date('Y-m-d H:i:s'); }

        try {
            $sql = 'INSERT INTO users (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
            $pdo->prepare($sql)->execute($vals);
            $newId = (int)$pdo->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => $newId, 'name' => $old['name'], 'email' => $old['email'], 'role' => $residentRole];
            unset($_SESSION['csrf_reg']);
            header('Location: ' . app_url('user/dashboard.php'));
            exit;
        } catch (PDOException $e) {
            error_log('register.php insert failed: ' . $e->getMessage());
            $errors[] = 'We could not create your account. Please try again, or contact the barangay office.';
        }
    }
}
$e = fn(string $k) => $errors[$k] ?? null;

$title = 'Create an account';
include __DIR__ . '/../includes/header.php';
?>
<style>
.bd { --ink:#0b2a5b; --sun:#f2b705; --mist:#eef1f6; --line:#d9dfea; --muted:#5b6678;
      font-family:'Source Sans 3',system-ui,sans-serif; color:var(--ink); max-width:680px; margin:0 auto; }
.bd h1 { font-family:'Bricolage Grotesque',system-ui,sans-serif; }
.bd-mast { border-left:10px solid var(--sun); padding:4px 0 4px 22px; margin-bottom:24px; }
.bd-mast h1 { font-size:clamp(1.8rem,4vw,2.5rem); font-weight:800; letter-spacing:-.02em; margin:0; line-height:1.05; }
.bd-mast p { color:var(--muted); margin:8px 0 0; }
.bd-box { border:2px solid var(--ink); border-radius:12px; padding:26px 28px; background:#fff; }
.bd-box label { display:block; font-weight:600; margin-bottom:5px; }
.bd-box input, .bd-box textarea { width:100%; border:2px solid var(--line); border-radius:8px; padding:10px 12px; font:inherit; color:var(--ink); }
.bd-box textarea { min-height:78px; resize:vertical; }
.bd-box input:focus-visible, .bd-box textarea:focus-visible { outline:3px solid var(--sun); border-color:var(--ink); }
.bd-box .bd-bad { border-color:#c8312b; }
.bd-field { margin-bottom:18px; }
.bd-msg { color:#8a1f1a; font-size:.92rem; margin-top:5px; }
.bd-hint { color:var(--muted); font-size:.92rem; margin-top:5px; }
.bd-top { background:#fde0de; color:#8a1f1a; padding:12px 16px; border-radius:6px; margin-bottom:18px; font-weight:600; }
.bd-go { background:var(--ink); color:#fff; border:0; border-radius:8px; padding:12px 26px; font:inherit; font-weight:700; cursor:pointer; }
.bd-go:hover { background:#14408a; } .bd-go:focus-visible { outline:3px solid var(--sun); outline-offset:2px; }
.bd-alt { margin-top:18px; color:var(--muted); } .bd-alt a { color:var(--ink); font-weight:600; }
</style>

<div class="bd">
    <header class="bd-mast">
        <h1>Create your account</h1>
        <p>Register once to request barangay documents and follow their progress.</p>
    </header>

    <?php if ($missing): ?>
        <div class="bd-top" role="alert">Registration is not available yet: the users table needs name, email and password columns. Please contact the system administrator.</div>
    <?php else: ?>
    <div class="bd-box">
        <?php foreach ($errors as $k => $msg) if (is_int($k)): ?><div class="bd-top" role="alert"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

        <form method="post" action="register.php" novalidate>
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_reg'] ?>">

            <div class="bd-field">
                <label for="name">Full name</label>
                <input id="name" name="name" autocomplete="name" required maxlength="100" value="<?= htmlspecialchars($old['name']) ?>" class="<?= $e('name') ? 'bd-bad' : '' ?>">
                <?php if ($e('name')): ?><div class="bd-msg"><?= $e('name') ?></div><?php endif; ?>
            </div>

            <div class="bd-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars($old['email']) ?>" class="<?= $e('email') ? 'bd-bad' : '' ?>">
                <?php if ($e('email')): ?><div class="bd-msg"><?= $e('email') ?></div><?php endif; ?>
            </div>

            <?php if ($phoneCol): ?>
            <div class="bd-field">
                <label for="phone">Contact number</label>
                <input id="phone" type="tel" name="phone" autocomplete="tel" required value="<?= htmlspecialchars($old['phone']) ?>" class="<?= $e('phone') ? 'bd-bad' : '' ?>">
                <?php if ($e('phone')): ?><div class="bd-msg"><?= $e('phone') ?></div><?php else: ?><div class="bd-hint">The barangay office may call you when your document is ready.</div><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($addrCol): ?>
            <div class="bd-field">
                <label for="address">Address in the barangay</label>
                <textarea id="address" name="address" autocomplete="street-address" required maxlength="255" class="<?= $e('address') ? 'bd-bad' : '' ?>"><?= htmlspecialchars($old['address']) ?></textarea>
                <?php if ($e('address')): ?><div class="bd-msg"><?= $e('address') ?></div><?php else: ?><div class="bd-hint">House number, street and purok or sitio.</div><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6 bd-field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="new-password" required minlength="8" class="<?= $e('password') ? 'bd-bad' : '' ?>">
                    <?php if ($e('password')): ?><div class="bd-msg"><?= $e('password') ?></div><?php else: ?><div class="bd-hint">At least 8 characters.</div><?php endif; ?>
                </div>
                <div class="col-md-6 bd-field">
                    <label for="password2">Confirm password</label>
                    <input id="password2" type="password" name="password2" autocomplete="new-password" required class="<?= $e('password2') ? 'bd-bad' : '' ?>">
                    <?php if ($e('password2')): ?><div class="bd-msg"><?= $e('password2') ?></div><?php endif; ?>
                </div>
            </div>

            <button class="bd-go" type="submit">Create account</button>
        </form>

        <p class="bd-alt">Already registered? <a href="login.php">Log in</a></p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>