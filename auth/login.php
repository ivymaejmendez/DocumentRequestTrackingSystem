<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$base = '/document-request-system';
$home = fn(array $u): string => $base . '/' . (($u['role'] ?? '') === 'admin' ? 'admin' : 'user') . '/dashboard.php';

if (!empty($_SESSION['user'])) {
    header('Location: ' . $home($_SESSION['user']));
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    if ($u && password_verify($password, $u['password'])) {
        unset($u['password']);
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        header('Location: ' . $home($u));
        exit;
    }

    $error = 'The email or password is not correct. Check them and try again.';
}

$title = 'Log in';
include __DIR__ . '/../includes/header.php';
?>
<style>
.lg { --ink:#0b2a5b; --sun:#f2b705; --mist:#eef1f6; --line:#d9dfea; --muted:#5b6678;
      font-family:'Source Sans 3',system-ui,sans-serif; color:var(--ink);
      max-width:940px; margin:24px auto; display:flex; border:2px solid var(--ink); border-radius:16px; overflow:hidden; background:#fff; }
.lg h1,.lg h2 { font-family:'Bricolage Grotesque',system-ui,sans-serif; }

.lg-side { flex:0 0 42%; background:var(--ink); color:#fff; padding:44px 38px; border-right:8px solid var(--sun); }
.lg-side h1 { font-size:clamp(1.7rem,3vw,2.3rem); font-weight:800; letter-spacing:-.02em; line-height:1.08; margin:0 0 14px; }
.lg-side p { color:#b8c4dc; margin:0 0 26px; }
.lg-side ul { list-style:none; margin:0; padding:0; }
.lg-side li { display:flex; gap:12px; align-items:flex-start; margin-bottom:16px; color:#e4eaf6; }
.lg-side li i { color:var(--sun); font-size:1.1rem; margin-top:2px; }

.lg-main { flex:1; padding:44px 42px; display:flex; flex-direction:column; justify-content:center; }
.lg-main h2 { font-size:1.8rem; font-weight:800; letter-spacing:-.01em; margin:0 0 6px; }
.lg-main .lg-sub { color:var(--muted); margin:0 0 24px; }
.lg-err { background:#fde0de; color:#8a1f1a; padding:12px 16px; border-radius:6px; margin-bottom:18px; font-weight:600; }

.lg label { display:block; font-weight:600; margin-bottom:5px; }
.lg-field { margin-bottom:18px; position:relative; }
.lg input[type=email], .lg input[type=password], .lg input[type=text] {
    width:100%; border:2px solid var(--line); border-radius:8px; padding:11px 14px; font:inherit; color:var(--ink); }
.lg input:focus-visible { outline:3px solid var(--sun); border-color:var(--ink); }
.lg-eye { position:absolute; right:6px; bottom:5px; border:0; background:transparent; color:var(--muted); padding:7px 10px; cursor:pointer; border-radius:6px; }
.lg-eye:hover { color:var(--ink); } .lg-eye:focus-visible { outline:3px solid var(--sun); }
.lg-go { width:100%; background:var(--ink); color:#fff; border:0; border-radius:8px; padding:13px; font:inherit; font-weight:700; cursor:pointer; }
.lg-go:hover { background:#14408a; } .lg-go:focus-visible { outline:3px solid var(--sun); outline-offset:2px; }
.lg-alt { margin:20px 0 0; color:var(--muted); } .lg-alt a { color:var(--ink); font-weight:600; }

@media (max-width:768px) {
    .lg { flex-direction:column; margin:8px auto; }
    .lg-side { flex:none; padding:28px 24px; border-right:0; border-bottom:8px solid var(--sun); }
    .lg-side ul { display:none; } .lg-side p { margin:0; }
    .lg-main { padding:30px 24px; }
}
</style>

<div class="lg">
    <aside class="lg-side">
        <h1>Barangay Document Request System</h1>
        <p>Ask for your barangay documents online and follow them until they are ready.</p>
        <ul>
            <li><i class="bi bi-file-earmark-text"></i> Request clearances and certificates without lining up</li>
            <li><i class="bi bi-signpost-split"></i> See each request move from Pending to Completed</li>
            <li><i class="bi bi-bag-check"></i> Pick up your document when it is marked ready</li>
        </ul>
    </aside>

    <section class="lg-main">
        <h2>Log in</h2>
        <p class="lg-sub">Use the email you registered with.</p>

        <?php if ($error): ?><div class="lg-err" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post" action="login.php">
            <div class="lg-field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" autocomplete="email" required autofocus value="<?= htmlspecialchars($email) ?>">
            </div>

            <div class="lg-field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required style="padding-right:56px">
                <button type="button" class="lg-eye" id="eye" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>

            <button class="lg-go" type="submit">Log in</button>
        </form>

        <p class="lg-alt">New resident? <a href="register.php">Create an account</a></p>
    </section>
</div>

<script>
document.getElementById('eye').addEventListener('click', function () {
    var f = document.getElementById('password'), show = f.type === 'password';
    f.type = show ? 'text' : 'password';
    this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    this.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>