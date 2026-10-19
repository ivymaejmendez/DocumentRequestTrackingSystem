<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SESSION['user']['role'] === 'admin') {
    header('Location:/document-request-system/admin/dashboard.php');
    exit;
}
$userId = (int)$_SESSION['user']['id'];

/* Filter by status */
$allowed = ['Pending', 'Processing', 'Completed'];
$f = in_array($_GET['status'] ?? '', $allowed, true) ? $_GET['status'] : '';

$counts = $pdo->prepare("SELECT status, COUNT(*) FROM document_requests WHERE user_id = ? GROUP BY status");
$counts->execute([$userId]);
$counts = $counts->fetchAll(PDO::FETCH_KEY_PAIR);
$all = array_sum($counts);

$sql = "SELECT dr.id, dr.reference_no, dr.status, dr.purpose, dr.quantity, dr.requested_at, dt.name AS doc, dt.processing_days
        FROM document_requests dr
        LEFT JOIN document_types dt ON dr.document_type_id = dt.id
        WHERE dr.user_id = ?" . ($f ? " AND dr.status = ?" : "") . "
        ORDER BY dr.requested_at DESC, dr.id DESC LIMIT 50";
$st = $pdo->prepare($sql);
$st->execute($f ? [$userId, $f] : [$userId]);
$requests = $st->fetchAll(PDO::FETCH_ASSOC);

/* Status history for just these requests */
$history = [];
if ($requests) {
    $hCols = array_column($pdo->query("SHOW COLUMNS FROM request_status_history")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $when = null;
    foreach (['changed_at','created_at','updated_at','date_changed','timestamp'] as $c) if (in_array($c, $hCols, true)) { $when = $c; break; }

    $ids = array_map(fn($r) => (int)$r['id'], $requests);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $hs = $pdo->prepare("SELECT request_id, status, remarks" . ($when ? ", `$when` AS at" : "") . "
                         FROM request_status_history WHERE request_id IN ($in)
                         ORDER BY request_id" . ($when ? ", `$when` DESC" : ""));
    $hs->execute($ids);
    foreach ($hs->fetchAll(PDO::FETCH_ASSOC) as $h) $history[$h['request_id']][] = $h;
}

$steps = ['Pending', 'Processing', 'Completed'];
$qs = fn($s) => $s ? '?status=' . urlencode($s) : 'my_requests.php';

$title = 'My Requests';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Source+Sans+3:wght@400;600&display=swap">
<style>
.bd { --ink:#0b2a5b; --sun:#f2b705; --mist:#eef1f6; --line:#d9dfea; --muted:#5b6678;
      font-family:'Source Sans 3',system-ui,sans-serif; color:var(--ink); }
.bd h1,.bd h2,.bd h3 { font-family:'Bricolage Grotesque',system-ui,sans-serif; }
.bd-mast { border-left:10px solid var(--sun); padding:4px 0 4px 22px; margin-bottom:24px; }
.bd-mast h1 { font-size:clamp(1.8rem,4vw,2.5rem); font-weight:800; letter-spacing:-.02em; margin:0; line-height:1.05; }
.bd-mast p { color:var(--muted); margin:8px 0 0; }
.bd-mast .bd-btn { display:inline-block; margin-top:14px; }
.bd-btn { background:var(--ink); color:#fff; border-radius:6px; padding:10px 20px; font-weight:600; text-decoration:none; }
.bd-btn:hover { background:#14408a; color:#fff; }
.bd :focus-visible { outline:3px solid var(--sun); outline-offset:2px; }

.bd-tabs { display:flex; flex-wrap:wrap; gap:6px; border-bottom:2px solid var(--ink); margin-bottom:22px; }
.bd-tabs a { padding:9px 16px; text-decoration:none; color:var(--ink); font-weight:600; border-radius:6px 6px 0 0; }
.bd-tabs a span { color:var(--muted); font-weight:400; margin-left:4px; }
.bd-tabs a:hover { background:var(--mist); }
.bd-tabs a.on { background:var(--ink); color:#fff; } .bd-tabs a.on span { color:var(--sun); }

.bd-req { border:2px solid var(--ink); border-radius:12px; padding:20px 22px; margin-bottom:16px; background:#fff; }
.bd-head { display:flex; flex-wrap:wrap; justify-content:space-between; gap:6px 16px; align-items:baseline; }
.bd-head h2 { font-size:1.2rem; font-weight:800; margin:0; }
.bd-ref { font-weight:600; color:var(--muted); }
.bd-meta { color:var(--muted); margin:6px 0 16px; }

/* Stepper: the progress of one request at a glance */
.bd-steps { display:flex; list-style:none; margin:0 0 6px; padding:0; }
.bd-steps li { flex:1; position:relative; text-align:center; padding-top:30px; font-weight:600; font-size:.92rem; color:var(--muted); }
.bd-steps li::before { content:''; position:absolute; top:7px; left:50%; width:18px; height:18px; margin-left:-9px;
    border-radius:50%; background:#fff; border:3px solid var(--line); z-index:1; }
.bd-steps li::after { content:''; position:absolute; top:15px; left:-50%; width:100%; height:3px; background:var(--line); }
.bd-steps li:first-child::after { display:none; }
.bd-steps li.done { color:var(--ink); }
.bd-steps li.done::before { background:var(--ink); border-color:var(--ink); }
.bd-steps li.done::after { background:var(--ink); }
.bd-steps li.now::before { background:var(--sun); border-color:var(--ink); }

.bd-tag { display:inline-block; padding:3px 10px; border-radius:4px; font-size:.85rem; font-weight:600; background:var(--mist); color:var(--muted); }
.bd details { margin-top:14px; border-top:1px solid var(--line); padding-top:12px; }
.bd summary { cursor:pointer; font-weight:600; }
.bd-log { list-style:none; margin:12px 0 0; padding:0; }
.bd-log li { padding:8px 0 8px 16px; border-left:3px solid var(--sun); margin-left:4px; }
.bd-log small { color:var(--muted); display:block; }
.bd-empty { text-align:center; color:var(--muted); padding:44px 0; border:2px dashed var(--line); border-radius:12px; }
</style>

<div class="bd">
    <header class="bd-mast">
        <h1>My requests</h1>
        <p>Follow each document from the moment you send it until it is ready.</p>
        <a class="bd-btn" href="request.php">Request a document</a>
    </header>

    <nav class="bd-tabs" aria-label="Filter by status">
        <a href="<?= $qs('') ?>" class="<?= $f === '' ? 'on' : '' ?>">All <span><?= $all ?></span></a>
        <?php foreach ($allowed as $s): ?>
            <a href="<?= $qs($s) ?>" class="<?= $f === $s ? 'on' : '' ?>"><?= $s ?> <span><?= (int)($counts[$s] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$requests): ?>
        <div class="bd-empty">
            <?= $f ? "You have no $f requests." : "You have not requested any documents yet." ?>
            <div style="margin-top:12px"><a class="bd-btn" href="request.php">Request a document</a></div>
        </div>
    <?php endif; ?>

    <?php foreach ($requests as $r):
        $idx = array_search($r['status'], $steps, true);
        $log = $history[$r['id']] ?? []; ?>
        <article class="bd-req">
            <div class="bd-head">
                <h2><?= htmlspecialchars($r['doc'] ?? 'Document') ?></h2>
                <span class="bd-ref"><?= htmlspecialchars($r['reference_no']) ?></span>
            </div>
            <p class="bd-meta">
                Requested <?= $r['requested_at'] ? date('M j, Y', strtotime($r['requested_at'])) : '' ?>
                &middot; <?= (int)$r['quantity'] ?> cop<?= (int)$r['quantity'] === 1 ? 'y' : 'ies' ?>
                <?php if (!empty($r['purpose'])): ?>&middot; For: <?= htmlspecialchars($r['purpose']) ?><?php endif; ?>
            </p>

            <?php if ($idx !== false): ?>
                <ol class="bd-steps" aria-label="Progress: <?= htmlspecialchars($r['status']) ?>">
                    <?php foreach ($steps as $i => $s): ?>
                        <li class="<?= $i <= $idx ? 'done' : '' ?> <?= $i === $idx ? 'now' : '' ?>"><?= $s ?></li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($r['status'] === 'Completed'): ?>
                    <p class="bd-meta" style="margin:8px 0 0">Ready. Bring a valid ID to the barangay office to claim it.</p>
                <?php elseif ($r['processing_days']): ?>
                    <p class="bd-meta" style="margin:8px 0 0">Usually ready in <?= (int)$r['processing_days'] ?> working day<?= (int)$r['processing_days'] === 1 ? '' : 's' ?>.</p>
                <?php endif; ?>
            <?php else: ?>
                <span class="bd-tag"><?= htmlspecialchars($r['status']) ?></span>
            <?php endif; ?>

            <?php if ($log): ?>
            <details>
                <summary>History (<?= count($log) ?>)</summary>
                <ul class="bd-log">
                    <?php foreach ($log as $h): ?>
                        <li>
                            <strong><?= htmlspecialchars($h['status']) ?></strong>
                            <?php if (!empty($h['remarks'])): ?> - <?= htmlspecialchars($h['remarks']) ?><?php endif; ?>
                            <?php if (!empty($h['at'])): ?><small><?= date('M j, Y g:i A', strtotime($h['at'])) ?></small><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>