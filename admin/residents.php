<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

/* ---------- Detect the real columns of `users` ---------- */
$uCols = array_column($pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC), 'Field');
$pick = function (array $want) use ($uCols) {
    foreach ($want as $w) if (in_array($w, $uCols, true)) return $w;
    return null;
};
$nameCol   = $pick(['name','full_name','fullname','username','first_name']);
$emailCol  = $pick(['email','email_address']);
$phoneCol  = $pick(['contact_number','contact_no','contact','phone','phone_number','mobile']);
$addrCol   = $pick(['address','purok','street']);
$joinedCol = $pick(['created_at','registered_at','date_registered','date_created']);

$sel = ['u.id AS uid'];
$sel[] = $nameCol   ? "u.`$nameCol` AS nm"     : "CONCAT('Resident #', u.id) AS nm";
$sel[] = $emailCol  ? "u.`$emailCol` AS email" : "NULL AS email";
$sel[] = $phoneCol  ? "u.`$phoneCol` AS phone" : "NULL AS phone";
$sel[] = $addrCol   ? "u.`$addrCol` AS addr"   : "NULL AS addr";
$sel[] = $joinedCol ? "u.`$joinedCol` AS joined" : "NULL AS joined";

/* ---------- One resident: their requests ---------- */
$viewId = (int)($_GET['id'] ?? 0);
if ($viewId > 0) {
    $st = $pdo->prepare("SELECT " . implode(', ', $sel) . " FROM users u WHERE u.id = ? AND u.role <> 'admin'");
    $st->execute([$viewId]);
    $res = $st->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        $st = $pdo->prepare("
            SELECT dr.reference_no, dr.status, dr.purpose, dr.quantity, dr.requested_at, dt.name AS doc
            FROM document_requests dr
            LEFT JOIN document_types dt ON dr.document_type_id = dt.id
            WHERE dr.user_id = ?
            ORDER BY dr.requested_at DESC");
        $st->execute([$viewId]);
        $history = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* ---------- Resident list ---------- */
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$where = ["u.role <> 'admin'"]; $args = [];
if ($q !== '') {
    $like = '%' . $q . '%'; $parts = [];
    foreach ([$nameCol, $emailCol, $phoneCol, $addrCol] as $c) if ($c) { $parts[] = "u.`$c` LIKE ?"; $args[] = $like; }
    if ($parts) $where[] = '(' . implode(' OR ', $parts) . ')';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$st = $pdo->prepare("SELECT COUNT(*) FROM users u $whereSql");
$st->execute($args);
$found = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($found / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$orderBy = $nameCol ? "u.`$nameCol` ASC" : 'u.id DESC';

$st = $pdo->prepare("
    SELECT " . implode(', ', $sel) . ",
           COUNT(dr.id) AS total,
           COALESCE(SUM(dr.status = 'Pending'), 0) AS pending
    FROM users u
    LEFT JOIN document_requests dr ON dr.user_id = u.id
    $whereSql
    GROUP BY u.id
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset");
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$totalResidents = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role <> 'admin'")->fetchColumn();
$link = fn(array $p) => 'residents.php' . ($p ? '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null)) : '');

$title = 'Residents';
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Source+Sans+3:wght@400;600&display=swap">
<style>
.bd { --ink:#0b2a5b; --sun:#f2b705; --mist:#eef1f6; --line:#d9dfea; --muted:#5b6678;
      font-family:'Source Sans 3',system-ui,sans-serif; color:var(--ink); }
.bd h1,.bd h2 { font-family:'Bricolage Grotesque',system-ui,sans-serif; }
.bd-mast { border-left:10px solid var(--sun); padding:4px 0 4px 22px; margin-bottom:24px; }
.bd-mast h1 { font-size:clamp(1.8rem,4vw,2.5rem); font-weight:800; letter-spacing:-.02em; margin:0; line-height:1.05; }
.bd-mast p { color:var(--muted); margin:8px 0 0; }
.bd-bar-f { display:flex; flex-wrap:wrap; gap:10px; padding:0 0 16px; }
.bd-bar-f input { flex:1 1 240px; border:2px solid var(--line); border-radius:6px; padding:8px 12px; font:inherit; color:var(--ink); }
.bd-btn { background:var(--ink); color:#fff; border:0; border-radius:6px; padding:9px 18px; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
.bd-btn:hover { background:#14408a; color:#fff; }
.bd-btn.ghost { background:transparent; color:var(--ink); border:2px solid var(--line); }
.bd :focus-visible { outline:3px solid var(--sun); outline-offset:2px; }
.bd-count { color:var(--muted); margin:0 0 12px; }
.bd-panel { border:2px solid var(--ink); border-radius:10px; padding:6px 20px 14px; background:#fff; overflow-x:auto; margin-bottom:20px; }
.bd-table { width:100%; border-collapse:collapse; font-size:.95rem; min-width:700px; }
.bd-table th { text-align:left; font-weight:600; color:var(--muted); padding:14px 10px 10px 0; border-bottom:2px solid var(--ink); }
.bd-table td { padding:12px 10px 12px 0; border-bottom:1px solid var(--line); vertical-align:middle; }
.bd-table tr:last-child td { border-bottom:0; }
.bd-name { font-weight:600; color:var(--ink); text-decoration:none; }
.bd-name:hover { text-decoration:underline; }
.bd-muted { color:var(--muted); }
.bd-tag { display:inline-block; padding:3px 10px; border-radius:4px; font-size:.85rem; font-weight:600; }
.bd-t-Pending{background:#fff1c2;color:#7a5a00} .bd-t-Processing{background:#dbe9ff;color:#0b3d91}
.bd-t-Completed{background:#d6f0e2;color:#14583a} .bd-t-x{background:var(--mist);color:var(--muted)}
.bd-empty { padding:36px 0; text-align:center; color:var(--muted); }
.bd-card { background:var(--ink); color:#fff; border-radius:14px; padding:24px 28px; margin-bottom:22px; }
.bd-card h2 { font-size:1.5rem; font-weight:800; margin:0 0 10px; }
.bd-card dl { display:flex; flex-wrap:wrap; gap:8px 32px; margin:0; color:#d5ddee; }
.bd-card dt { font-weight:400; color:#b8c4dc; } .bd-card dd { margin:0; color:#fff; font-weight:600; }
.bd-card a { color:#fff; }
.bd-pg { display:flex; flex-wrap:wrap; align-items:center; gap:6px; color:var(--muted); }
.bd-pg a, .bd-pg b { padding:6px 12px; border-radius:6px; text-decoration:none; border:2px solid var(--line); color:var(--ink); }
.bd-pg b { background:var(--ink); border-color:var(--ink); color:#fff; }
</style>

<div class="bd">

<?php if ($viewId > 0 && !empty($res)): ?>

    <header class="bd-mast">
        <h1>Resident record</h1>
        <p><a href="<?= $link(['q' => $q]) ?>">Back to all residents</a></p>
    </header>

    <section class="bd-card">
        <h2><?= htmlspecialchars($res['nm']) ?></h2>
        <dl>
            <?php if ($emailCol): ?><div><dt>Email</dt><dd><?= htmlspecialchars($res['email'] ?? '-') ?></dd></div><?php endif; ?>
            <?php if ($phoneCol): ?><div><dt>Contact</dt><dd><?= htmlspecialchars($res['phone'] ?? '-') ?></dd></div><?php endif; ?>
            <?php if ($addrCol):  ?><div><dt>Address</dt><dd><?= htmlspecialchars($res['addr'] ?? '-') ?></dd></div><?php endif; ?>
            <?php if ($joinedCol && $res['joined']): ?><div><dt>Registered</dt><dd><?= date('M j, Y', strtotime($res['joined'])) ?></dd></div><?php endif; ?>
            <div><dt>Requests</dt><dd><?= count($history) ?></dd></div>
        </dl>
    </section>

    <div class="bd-panel">
        <?php if (!$history): ?>
            <p class="bd-empty">This resident has not requested any documents yet.</p>
        <?php else: ?>
        <table class="bd-table">
            <thead><tr><th>Reference</th><th>Document</th><th>Purpose</th><th>Copies</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h):
                $tag = in_array($h['status'], ['Pending','Processing','Completed'], true) ? $h['status'] : 'x'; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($h['reference_no']) ?></strong></td>
                    <td><?= htmlspecialchars($h['doc'] ?? '') ?></td>
                    <td class="bd-muted"><?= htmlspecialchars($h['purpose'] ?? '') ?></td>
                    <td><?= (int)$h['quantity'] ?></td>
                    <td><span class="bd-tag bd-t-<?= $tag ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                    <td><?= $h['requested_at'] ? date('M j, Y', strtotime($h['requested_at'])) : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php else: ?>

    <header class="bd-mast">
        <h1>Residents</h1>
        <p>Everyone registered in the system, with how many documents each has requested.</p>
    </header>

    <?php if ($viewId > 0): ?><div class="bd-empty">That resident was not found.</div><?php endif; ?>

    <form class="bd-bar-f" method="get" action="residents.php">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name<?= $emailCol ? ', email' : '' ?><?= $phoneCol ? ', contact' : '' ?><?= $addrCol ? ' or address' : '' ?>" aria-label="Search residents">
        <button class="bd-btn" type="submit">Search</button>
        <?php if ($q !== ''): ?><a class="bd-btn ghost" href="residents.php">Clear</a><?php endif; ?>
    </form>

    <p class="bd-count">
        <?= $q !== '' ? "$found of $totalResidents residents match" : "$totalResidents registered residents" ?>
    </p>

    <div class="bd-panel">
        <?php if (!$rows): ?>
            <p class="bd-empty"><?= $q !== '' ? 'No residents match that search.' : 'No residents have registered yet. Accounts appear here when residents sign up.' ?></p>
        <?php else: ?>
        <table class="bd-table">
            <thead>
                <tr>
                    <th>Resident</th>
                    <?php if ($emailCol): ?><th>Email</th><?php endif; ?>
                    <?php if ($phoneCol): ?><th>Contact</th><?php endif; ?>
                    <?php if ($addrCol):  ?><th>Address</th><?php endif; ?>
                    <?php if ($joinedCol): ?><th>Registered</th><?php endif; ?>
                    <th>Requests</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a class="bd-name" href="<?= $link(['id' => $r['uid'], 'q' => $q]) ?>"><?= htmlspecialchars($r['nm']) ?></a></td>
                    <?php if ($emailCol): ?><td class="bd-muted"><?= htmlspecialchars($r['email'] ?? '') ?></td><?php endif; ?>
                    <?php if ($phoneCol): ?><td class="bd-muted"><?= htmlspecialchars($r['phone'] ?? '') ?></td><?php endif; ?>
                    <?php if ($addrCol):  ?><td class="bd-muted"><?= htmlspecialchars($r['addr'] ?? '') ?></td><?php endif; ?>
                    <?php if ($joinedCol): ?><td class="bd-muted"><?= $r['joined'] ? date('M j, Y', strtotime($r['joined'])) : '' ?></td><?php endif; ?>
                    <td>
                        <?= (int)$r['total'] ?>
                        <?php if ((int)$r['pending'] > 0): ?><span class="bd-tag bd-t-Pending"><?= (int)$r['pending'] ?> pending</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="bd-pg" aria-label="Pages">
        <span>Showing <?= $offset + 1 ?>-<?= $offset + count($rows) ?> of <?= $found ?></span>
        <?php if ($page > 1): ?><a href="<?= $link(['q' => $q, 'page' => $page - 1]) ?>">Previous</a><?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <?= $i === $page ? "<b aria-current=\"page\">$i</b>" : '<a href="' . $link(['q' => $q, 'page' => $i]) . "\">$i</a>" ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="<?= $link(['q' => $q, 'page' => $page + 1]) ?>">Next</a><?php endif; ?>
    </nav>
    <?php endif; ?>

<?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>