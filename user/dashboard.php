<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SESSION['user']['role'] === 'admin') {
    header('Location: ' . app_url('admin/dashboard.php'));
    exit;
}

$userId   = (int)$_SESSION['user']['id'];
$userName = $_SESSION['user']['name'] ?? 'Resident';

/* Counts by status in one query */
$st = $pdo->prepare("SELECT status, COUNT(*) FROM document_requests WHERE user_id = ? GROUP BY status");
$st->execute([$userId]);
$by = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$total      = array_sum($by);
$pending    = (int)($by['Pending'] ?? 0);
$processing = (int)($by['Processing'] ?? 0);
$completed  = (int)($by['Completed'] ?? 0);

/* Most recent request */
$st = $pdo->prepare("SELECT dr.reference_no, dr.status, dr.requested_at, dt.name AS doc, dt.processing_days
                     FROM document_requests dr
                     LEFT JOIN document_types dt ON dr.document_type_id = dt.id
                     WHERE dr.user_id = ?
                     ORDER BY dr.requested_at DESC, dr.id DESC LIMIT 1");
$st->execute([$userId]);
$latest = $st->fetch(PDO::FETCH_ASSOC);

/* Document types come from the database, so new ones appear automatically */
$types = $pdo->query("SELECT id, name, description, processing_days FROM document_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

function doc_icon(string $name): string {
    $n = strtolower($name);
    foreach (['business'=>'bi-shop','clearance'=>'bi-patch-check','residen'=>'bi-house-check','indigen'=>'bi-person-check',
              'cedula'=>'bi-receipt','ctc'=>'bi-receipt','burial'=>'bi-flower1','job'=>'bi-briefcase','custom'=>'bi-file-earmark-plus'] as $k => $i)
        if (str_contains($n, $k)) return $i;
    return 'bi-file-earmark-text';
}

$first = trim(explode(' ', trim($userName))[0]) ?: 'Resident';
$title = 'My Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<style>
.bd { --ink:#0b2a5b; --sun:#f2b705; --mist:#eef1f6; --line:#d9dfea; --muted:#5b6678;
      font-family:'Source Sans 3',system-ui,sans-serif; color:var(--ink); }
.bd h1,.bd h2,.bd h3,.bd .bd-num { font-family:'Bricolage Grotesque',system-ui,sans-serif; }
.bd-mast { border-left:10px solid var(--sun); padding:4px 0 4px 22px; margin-bottom:26px; }
.bd-mast h1 { font-size:clamp(1.9rem,4vw,2.7rem); font-weight:800; letter-spacing:-.02em; margin:0; line-height:1.05; }
.bd-mast p { color:var(--muted); margin:8px 0 0; }
.bd-btn { display:inline-block; background:var(--ink); color:#fff; padding:10px 20px; border-radius:6px; text-decoration:none; font-weight:600; }
.bd-btn:hover { background:#14408a; color:#fff; }
.bd-mast .bd-btn { margin-top:16px; }
.bd :focus-visible { outline:3px solid var(--sun); outline-offset:2px; }

/* One strip, not four cards */
.bd-sum { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:18px; background:var(--ink); color:#fff;
          border-radius:14px; padding:24px 28px; margin-bottom:26px; }
.bd-sum .bd-num { font-size:2.4rem; font-weight:800; line-height:1; }
.bd-sum .bd-lab { color:#d5ddee; margin-top:6px; }
.bd-sum .bd-first .bd-num { color:var(--sun); }

.bd-latest { border:2px solid var(--ink); border-radius:12px; padding:18px 22px; margin-bottom:30px; background:#fff;
             display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px 20px; }
.bd-latest h2 { font-size:1.1rem; font-weight:800; margin:0 0 3px; }
.bd-latest p { margin:0; color:var(--muted); }
.bd-tag { display:inline-block; padding:3px 10px; border-radius:4px; font-size:.85rem; font-weight:600; background:var(--mist); color:var(--muted); }
.bd-t-Pending{background:#fff1c2;color:#7a5a00} .bd-t-Processing{background:#dbe9ff;color:#0b3d91} .bd-t-Completed{background:#d6f0e2;color:#14583a}
.bd-link { color:var(--ink); font-weight:600; }

.bd-sec { font-size:1.35rem; font-weight:800; margin:0 0 14px; }
.bd-doc { display:block; height:100%; border:2px solid var(--line); border-radius:10px; padding:20px; background:#fff; text-decoration:none; color:var(--ink); }
.bd-doc:hover { border-color:var(--ink); color:var(--ink); }
.bd-doc i { font-size:1.6rem; }
.bd-doc h3 { font-size:1.1rem; font-weight:700; margin:10px 0 4px; }
.bd-doc p { color:var(--muted); font-size:.95rem; margin:0 0 10px; }
.bd-doc small { font-weight:600; }
</style>

<div class="bd">
    <header class="bd-mast">
        <h1>Hello, <?= htmlspecialchars($first) ?></h1>
        <p>Request barangay documents and follow each one until it is ready.</p>
        <a class="bd-btn" href="request.php">Request a document</a>
    </header>

    <section class="bd-sum" aria-label="Your requests">
        <div class="bd-first"><div class="bd-num"><?= $total ?></div><div class="bd-lab">All requests</div></div>
        <div><div class="bd-num"><?= $pending ?></div><div class="bd-lab">Pending</div></div>
        <div><div class="bd-num"><?= $processing ?></div><div class="bd-lab">Processing</div></div>
        <div><div class="bd-num"><?= $completed ?></div><div class="bd-lab">Completed</div></div>
    </section>

    <?php if ($latest):
        $tag = in_array($latest['status'], ['Pending','Processing','Completed'], true) ? 'bd-t-' . $latest['status'] : ''; ?>
    <section class="bd-latest" aria-label="Latest request">
        <div>
            <h2><?= htmlspecialchars($latest['doc'] ?? 'Document') ?></h2>
            <p>
                <?= htmlspecialchars($latest['reference_no']) ?>
                <?php if ($latest['requested_at']): ?>&middot; requested <?= date('M j, Y', strtotime($latest['requested_at'])) ?><?php endif; ?>
            </p>
        </div>
        <div>
            <span class="bd-tag <?= $tag ?>"><?= htmlspecialchars($latest['status']) ?></span>
            &nbsp; <a class="bd-link" href="my_requests.php">See all my requests</a>
        </div>
    </section>
    <?php endif; ?>

    <h2 class="bd-sec">Choose a document</h2>
    <div class="row g-3">
        <?php foreach ($types as $t): ?>
        <div class="col-md-6 col-lg-4">
            <a class="bd-doc" href="request.php?type=<?= (int)$t['id'] ?>#form">
                <i class="bi <?= doc_icon($t['name']) ?>"></i>
                <h3><?= htmlspecialchars($t['name']) ?></h3>
                <p><?= htmlspecialchars($t['description'] ?? '') ?></p>
                <small><?= (int)$t['processing_days'] ?> working day<?= (int)$t['processing_days'] === 1 ? '' : 's' ?></small>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>