<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SESSION['user']['role'] === 'admin') {
    header('Location: ' . app_url('admin/dashboard.php'));
    exit;
}
if (empty($_SESSION['csrf_new'])) $_SESSION['csrf_new'] = bin2hex(random_bytes(16));

$userId = (int)$_SESSION['user']['id'];
$types  = $pdo->query("SELECT * FROM document_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

function doc_meta(string $name): array {
    $n = strtolower($name);
    foreach ([
        'business' => ['Business',     'bi-shop'],
        'clearance'=> ['Clearance',    'bi-patch-check'],
        'residen'  => ['Residency',    'bi-house-check'],
        'indigen'  => ['Indigency',    'bi-person-check'],
        'cedula'   => ['CTC / Cedula', 'bi-receipt'],
        'ctc'      => ['CTC / Cedula', 'bi-receipt'],
        'burial'   => ['Burial',       'bi-flower1'],
        'job'      => ['Job Seeker',   'bi-briefcase'],
        'custom'   => ['Custom',       'bi-file-earmark-plus'],
    ] as $key => $m) if (str_contains($n, $key)) return $m;
    return [$name, 'bi-file-earmark-text'];
}

$selected = null;
$wantId = (int)($_GET['type'] ?? $_POST['document_type_id'] ?? 0);
foreach ($types as $t) if ((int)$t['id'] === $wantId) $selected = $t;

/* ---------- Submit ---------- */
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose = trim($_POST['purpose'] ?? '');
    $qty     = min(10, max(1, (int)($_POST['quantity'] ?? 1)));

    if (!hash_equals($_SESSION['csrf_new'], $_POST['csrf'] ?? '')) {
        $error = 'Your session expired. Reload the page and try again.';
    } elseif (!$selected) {
        $error = 'Choose a document before submitting.';
    } elseif ($purpose === '') {
        $error = 'Tell us what you need this document for.';
    } else {
        $ref = 'DRS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO document_requests
                (reference_no, user_id, document_type_id, purpose, quantity, status, requested_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), NOW())")
                ->execute([$ref, $userId, (int)$selected['id'], mb_substr($purpose, 0, 500), $qty]);
            $id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO request_status_history (request_id, status, remarks, changed_by) VALUES (?, 'Pending', 'Request submitted', ?)")
                ->execute([$id, $userId]);
            $pdo->commit();

            $_SESSION['flash_user'] = [$selected['name'], $ref];
            header('Location: request.php');
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('request.php insert failed: ' . $e->getMessage());
            $error = 'We could not send your request. Please try again, or contact the barangay office.';
        }
    }
}
$sent = $_SESSION['flash_user'] ?? null;
unset($_SESSION['flash_user']);

$cats = [];
foreach ($types as $t) { $cats[doc_meta($t['name'])[0]] = true; }

$title = 'Request a Document';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Source+Sans+3:wght@400;600&display=swap">
<style>
.bd { --ink:#0b2a5b; --sun:#f2b705; --mist:#eef1f6; --line:#d9dfea; --muted:#5b6678;
      font-family:'Source Sans 3',system-ui,sans-serif; color:var(--ink); }
.bd h1,.bd h2,.bd h3 { font-family:'Bricolage Grotesque',system-ui,sans-serif; }
.bd-mast { border-left:10px solid var(--sun); padding:4px 0 4px 22px; margin-bottom:24px; }
.bd-mast h1 { font-size:clamp(1.8rem,4vw,2.5rem); font-weight:800; letter-spacing:-.02em; margin:0; line-height:1.05; }
.bd-mast p { color:var(--muted); margin:8px 0 0; }

.bd-ok { background:#d6f0e2; color:#14583a; border-radius:10px; padding:16px 20px; margin-bottom:22px; }
.bd-ok strong { font-family:'Bricolage Grotesque',system-ui,sans-serif; font-size:1.25rem; letter-spacing:.02em; }
.bd-ok a { color:#14583a; font-weight:600; }
.bd-err { background:#fde0de; color:#8a1f1a; padding:12px 16px; border-radius:6px; margin-bottom:18px; font-weight:600; }

.bd-form { background:var(--ink); color:#fff; border-radius:14px; padding:26px 28px; margin-bottom:28px; }
.bd-form h2 { font-size:1.4rem; font-weight:800; margin:0 0 4px; }
.bd-form .bd-sub { color:#b8c4dc; margin:0 0 16px; }
.bd-facts { display:flex; flex-wrap:wrap; gap:8px 22px; margin:0 0 18px; color:#d5ddee; }
.bd-form label { display:block; font-weight:600; margin-bottom:6px; }
.bd-form textarea, .bd-form input[type=number] { width:100%; border:0; border-radius:8px; padding:12px; font:inherit; color:var(--ink); }
.bd-form textarea { min-height:96px; resize:vertical; }
.bd-form input[type=number] { max-width:120px; }
.bd-form :is(textarea,input):focus-visible { outline:3px solid var(--sun); }
.bd-go { background:var(--sun); color:var(--ink); border:0; border-radius:8px; padding:11px 24px; font:inherit; font-weight:700; cursor:pointer; }
.bd-go:hover { background:#ffc928; }
.bd-go:focus-visible, .bd-link:focus-visible { outline:3px solid #fff; outline-offset:2px; }
.bd-link { color:#d5ddee; margin-left:14px; }

.bd-chips { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 20px; }
.bd-chips button { border:2px solid var(--line); background:#fff; color:var(--ink); border-radius:50px; padding:7px 16px; font:inherit; font-weight:600; cursor:pointer; }
.bd-chips button:hover { border-color:var(--ink); }
.bd-chips button.on { background:var(--ink); border-color:var(--ink); color:#fff; }
.bd-chips button:focus-visible { outline:3px solid var(--sun); outline-offset:2px; }

.bd-doc { display:block; height:100%; border:2px solid var(--line); border-radius:10px; padding:20px; background:#fff; text-decoration:none; color:var(--ink); }
.bd-doc:hover { border-color:var(--ink); color:var(--ink); }
.bd-doc:focus-visible { outline:3px solid var(--sun); outline-offset:2px; }
.bd-doc.on { border-color:var(--ink); box-shadow:inset 6px 0 0 var(--sun); }
.bd-doc i { font-size:1.6rem; }
.bd-doc h3 { font-size:1.1rem; font-weight:700; margin:10px 0 4px; }
.bd-doc p { color:var(--muted); font-size:.95rem; margin:0 0 10px; }
.bd-doc small { font-weight:600; }
[hidden] { display:none !important; }
</style>

<div class="bd">
    <header class="bd-mast">
        <h1>Request a document</h1>
        <p>Choose what you need. Follow its progress anytime under My Requests.</p>
    </header>

    <?php if ($sent): ?>
        <div class="bd-ok" role="status">
            Request sent for <?= htmlspecialchars($sent[0]) ?>.<br>
            Your reference number is <strong><?= htmlspecialchars($sent[1]) ?></strong>.
            <a href="my_requests.php">Track this request</a>
        </div>
    <?php endif; ?>
    <?php if ($error): ?><div class="bd-err" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($selected): ?>
    <section class="bd-form" id="form" aria-label="Request form">
        <h2><?= htmlspecialchars($selected['name']) ?></h2>
        <p class="bd-sub"><?= htmlspecialchars($selected['description'] ?? '') ?></p>
        <div class="bd-facts">
            <span><i class="bi bi-clock"></i> Usually ready in <?= (int)$selected['processing_days'] ?> working day<?= (int)$selected['processing_days'] === 1 ? '' : 's' ?></span>
            <span><i class="bi bi-card-heading"></i> Bring a valid ID when you pick it up</span>
        </div>
        <form method="post" action="request.php">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_new'] ?>">
            <input type="hidden" name="document_type_id" value="<?= (int)$selected['id'] ?>">

            <label for="purpose"><?= stripos($selected['name'], 'custom') !== false ? 'Describe the certificate you need' : 'What do you need it for?' ?></label>
            <textarea id="purpose" name="purpose" maxlength="500" required
                placeholder="Example: employment requirement, school enrollment, loan application"><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>

            <label for="quantity" style="margin-top:14px">How many copies?</label>
            <input id="quantity" type="number" name="quantity" min="1" max="10" value="<?= (int)($_POST['quantity'] ?? 1) ?>" required>

            <div style="margin-top:20px">
                <button class="bd-go" type="submit">Send request</button>
                <a class="bd-link" href="request.php">Choose a different document</a>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <div class="bd-chips" role="group" aria-label="Filter documents">
        <button type="button" class="on" data-cat="all">All</button>
        <?php foreach (array_keys($cats) as $c): ?>
            <button type="button" data-cat="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="row g-3" id="docs">
        <?php foreach ($types as $t): [$label, $icon] = doc_meta($t['name']); ?>
        <div class="col-md-6 col-lg-4" data-cat="<?= htmlspecialchars($label) ?>">
            <a class="bd-doc <?= $selected && (int)$selected['id'] === (int)$t['id'] ? 'on' : '' ?>"
               href="request.php?type=<?= (int)$t['id'] ?>#form">
                <i class="bi <?= $icon ?>"></i>
                <h3><?= htmlspecialchars($t['name']) ?></h3>
                <p><?= htmlspecialchars($t['description'] ?? '') ?></p>
                <small><?= (int)$t['processing_days'] ?> working day<?= (int)$t['processing_days'] === 1 ? '' : 's' ?></small>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.querySelectorAll('.bd-chips button').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.bd-chips button').forEach(function (b) { b.classList.remove('on'); });
        btn.classList.add('on');
        var cat = btn.dataset.cat;
        document.querySelectorAll('#docs > [data-cat]').forEach(function (card) {
            card.hidden = !(cat === 'all' || card.dataset.cat === cat);
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>