<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

/* ---------- Counts (uses only the `status` column you already have) ---------- */
$total      = (int)$pdo->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$pending    = (int)$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status='Pending'")->fetchColumn();
$processing = (int)$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status='Processing'")->fetchColumn();
$completed  = (int)$pdo->query("SELECT COUNT(*) FROM document_requests WHERE status='Completed'")->fetchColumn();
$other      = max(0, $total - $pending - $processing - $completed);

$pct = fn(int $n): float => $total > 0 ? round($n / $total * 100, 1) : 0;

/* ---------- Requests per document type ---------- */
$byType = $pdo->query("
    SELECT dt.name, COUNT(dr.document_type_id) AS c
    FROM document_types dt
    LEFT JOIN document_requests dr ON dr.document_type_id = dt.id
    GROUP BY dt.id, dt.name
    ORDER BY c DESC, dt.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$maxType = max(1, (int)($byType[0]['c'] ?? 0));

/* ---------- Recent requests: detect real column names instead of guessing ---------- */
$cols = fn(string $t): array => array_column($pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC), 'Field');
$pick = function (array $have, array $want) {
    foreach ($want as $w) if (in_array($w, $have, true)) return $w;
    return null;
};
$drCols = $cols('document_requests');
$uCols  = $cols('users');

$dateCol = $pick($drCols, ['created_at','requested_at','date_requested','submitted_at','request_date','date_created','date']);
$refCol  = $pick($drCols, ['reference_no','reference_number','ref_no','reference','tracking_no','tracking_number','request_no']);
$nameCol = $pick($uCols,  ['name','full_name','fullname','username','first_name']);

$sel   = ["dr.status AS status", "dt.name AS document_name", "dr.id AS rid"];
$sel[] = $refCol  ? "dr.`$refCol` AS ref"       : "CONCAT('#', dr.id) AS ref";
$sel[] = $dateCol ? "dr.`$dateCol` AS made_on"  : "NULL AS made_on";
$sel[] = $nameCol ? "u.`$nameCol` AS who"       : "CONCAT('User ', dr.user_id) AS who";
$order = $dateCol ? "dr.`$dateCol` DESC" : "dr.id DESC";

$recent = $pdo->query("
    SELECT " . implode(', ', $sel) . "
    FROM document_requests dr
    LEFT JOIN users u ON dr.user_id = u.id
    LEFT JOIN document_types dt ON dr.document_type_id = dt.id
    ORDER BY $order
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$title = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Source+Sans+3:wght@400;600&display=swap">
<style>
.bd {
    --ink:#0b2a5b; --sun:#f2b705; --brick:#c8312b; --leaf:#1f7a4d;
    --mist:#eef1f6; --line:#d9dfea; --muted:#5b6678;
    font-family:'Source Sans 3',system-ui,sans-serif; color:var(--ink);
}
.bd h1,.bd h2,.bd .bd-num { font-family:'Bricolage Grotesque',system-ui,sans-serif; }

/* Masthead */
.bd-mast { border-left:10px solid var(--sun); padding:4px 0 4px 22px; margin-bottom:28px; }
.bd-mast h1 { font-size:clamp(1.9rem,4vw,2.8rem); font-weight:800; letter-spacing:-.02em; margin:0; line-height:1.05; }
.bd-mast p { color:var(--muted); margin:8px 0 0; max-width:60ch; }
.bd-mast .bd-btn { display:inline-block; margin-top:16px; background:var(--ink); color:#fff; padding:10px 20px;
    border-radius:6px; text-decoration:none; font-weight:600; }
.bd-mast .bd-btn:hover { background:#14408a; color:#fff; }
.bd-btn:focus-visible { outline:3px solid var(--sun); outline-offset:2px; }

/* The one memorable element: the queue strip */
.bd-queue { background:var(--ink); color:#fff; border-radius:14px; padding:26px 28px; margin-bottom:28px; }
.bd-queue h2 { font-size:1.15rem; font-weight:700; margin:0 0 4px; }
.bd-queue .bd-sub { color:#b8c4dc; margin:0 0 20px; }
.bd-strip { display:flex; height:34px; border-radius:6px; overflow:hidden; background:rgba(255,255,255,.12); gap:3px; }
.bd-strip span { display:block; min-width:0; transition:width .6s ease; }
.bd-s-pending{background:var(--sun)} .bd-s-processing{background:#6aa6ff}
.bd-s-completed{background:#4cc38a} .bd-s-other{background:#8a94a8}
.bd-legend { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:18px; margin-top:22px; }
.bd-legend .bd-num { font-size:2.6rem; font-weight:800; line-height:1; }
.bd-legend .bd-lab { display:flex; align-items:center; gap:8px; color:#d5ddee; margin-top:6px; }
.bd-dot { width:12px; height:12px; border-radius:3px; display:inline-block; }
.bd-legend .bd-total .bd-num { color:var(--sun); }

/* Lower panels */
.bd-panel { border:2px solid var(--ink); border-radius:10px; padding:22px; height:100%; background:#fff; }
.bd-panel h2 { font-size:1.15rem; font-weight:700; margin:0 0 16px; }
.bd-row { margin-bottom:14px; }
.bd-row .bd-top { display:flex; justify-content:space-between; font-size:.95rem; margin-bottom:5px; }
.bd-bar { height:10px; background:var(--mist); border-radius:5px; overflow:hidden; }
.bd-bar i { display:block; height:100%; background:var(--ink); border-radius:5px; }

.bd-table { width:100%; border-collapse:collapse; font-size:.95rem; }
.bd-table th { text-align:left; font-weight:600; color:var(--muted); padding:0 8px 8px 0; border-bottom:2px solid var(--ink); }
.bd-table td { padding:11px 8px 11px 0; border-bottom:1px solid var(--line); vertical-align:middle; }
.bd-ref { font-weight:600; }
.bd-tag { display:inline-block; padding:3px 10px; border-radius:4px; font-size:.85rem; font-weight:600; }
.bd-t-Pending{background:#fff1c2;color:#7a5a00} .bd-t-Processing{background:#dbe9ff;color:#0b3d91}
.bd-t-Completed{background:#d6f0e2;color:#14583a} .bd-t-x{background:var(--mist);color:var(--muted)}
.bd-empty { color:var(--muted); padding:18px 0; }
.bd-scroll { overflow-x:auto; }

@media (prefers-reduced-motion:reduce){ .bd-strip span{transition:none} }
</style>

<div class="bd">

    <header class="bd-mast">
        <h1>Barangay request desk</h1>
        <p>See what is waiting, what is being worked on, and what is ready for residents.</p>
        <a class="bd-btn" href="requests.php">Manage requests</a>
    </header>

    <section class="bd-queue" aria-label="Request status overview">
        <h2>Where requests stand</h2>
        <p class="bd-sub">
            <?= $total === 0 ? 'No requests have been submitted yet.' : "Share of all $total requests by status." ?>
        </p>

        <div class="bd-strip" role="img"
             aria-label="Pending <?= $pending ?>, processing <?= $processing ?>, completed <?= $completed ?>">
            <span class="bd-s-pending"    style="width:<?= $pct($pending) ?>%"></span>
            <span class="bd-s-processing" style="width:<?= $pct($processing) ?>%"></span>
            <span class="bd-s-completed"  style="width:<?= $pct($completed) ?>%"></span>
            <?php if ($other): ?><span class="bd-s-other" style="width:<?= $pct($other) ?>%"></span><?php endif; ?>
        </div>

        <div class="bd-legend">
            <div class="bd-total">
                <div class="bd-num"><?= $total ?></div>
                <div class="bd-lab">All requests</div>
            </div>
            <div>
                <div class="bd-num"><?= $pending ?></div>
                <div class="bd-lab"><span class="bd-dot bd-s-pending"></span>Pending</div>
            </div>
            <div>
                <div class="bd-num"><?= $processing ?></div>
                <div class="bd-lab"><span class="bd-dot bd-s-processing"></span>Processing</div>
            </div>
            <div>
                <div class="bd-num"><?= $completed ?></div>
                <div class="bd-lab"><span class="bd-dot bd-s-completed"></span>Completed</div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-5">
            <section class="bd-panel">
                <h2>Most requested documents</h2>
                <?php if (!$byType): ?>
                    <p class="bd-empty">No document types found.</p>
                <?php else: foreach ($byType as $t): ?>
                    <div class="bd-row">
                        <div class="bd-top">
                            <span><?= htmlspecialchars($t['name']) ?></span>
                            <strong><?= (int)$t['c'] ?></strong>
                        </div>
                        <div class="bd-bar"><i style="width:<?= round($t['c'] / $maxType * 100) ?>%"></i></div>
                    </div>
                <?php endforeach; endif; ?>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="bd-panel">
                <h2>Latest requests</h2>
                <?php if (!$recent): ?>
                    <p class="bd-empty">Nothing here yet. New requests from residents will show up first.</p>
                <?php else: ?>
                    <div class="bd-scroll">
                    <table class="bd-table">
                        <thead>
                            <tr><th>Reference</th><th>Resident</th><th>Document</th><th>Status</th><?php if ($dateCol): ?><th>Date</th><?php endif; ?></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent as $r):
                            $tag = in_array($r['status'], ['Pending','Processing','Completed'], true) ? $r['status'] : 'x'; ?>
                            <tr>
                                <td class="bd-ref"><?= htmlspecialchars($r['ref']) ?></td>
                                <td><?= htmlspecialchars($r['who'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['document_name'] ?? '') ?></td>
                                <td><span class="bd-tag bd-t-<?= $tag ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                                <?php if ($dateCol): ?>
                                    <td><?= $r['made_on'] ? date('M j, Y', strtotime($r['made_on'])) : '' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>