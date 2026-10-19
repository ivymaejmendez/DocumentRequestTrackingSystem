<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_req'])) $_SESSION['csrf_req'] = bin2hex(random_bytes(16));

/* ---------- Detect real column names (no guessing) ---------- */
$describe = fn(string $t): array => $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
$drInfo = $describe('document_requests');
$drCols = array_column($drInfo, 'Field');
$uCols  = array_column($describe('users'), 'Field');
$pick = function (array $have, array $want) {
    foreach ($want as $w) if (in_array($w, $have, true)) return $w;
    return null;
};
$dateCol = $pick($drCols, ['created_at','requested_at','date_requested','submitted_at','request_date','date_created','date']);
$refCol  = $pick($drCols, ['reference_no','reference_number','ref_no','reference','tracking_no','tracking_number','request_no']);
$nameCol = $pick($uCols,  ['name','full_name','fullname','username','first_name']);

/* Status choices: read them from the column if it is an ENUM, otherwise use the usual three */
$statuses = ['Pending','Processing','Completed'];
foreach ($drInfo as $c) {
    if ($c['Field'] === 'status' && preg_match('/^enum\((.*)\)$/i', $c['Type'], $m)) {
        $statuses = array_map(fn($v) => trim($v, "'"), explode(',', $m[1]));
    }
}

/* ---------- Update a status (POST, then redirect) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = hash_equals($_SESSION['csrf_req'], $_POST['csrf'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $new = $_POST['status'] ?? '';
    if ($ok && $id > 0 && in_array($new, $statuses, true)) {
        $pdo->prepare("UPDATE document_requests SET status = ? WHERE id = ?")->execute([$new, $id]);
        $_SESSION['flash_req'] = ['ok', "Request updated to $new."];
    } else {
        $_SESSION['flash_req'] = ['err', 'That change could not be saved. Reload the page and try again.'];
    }
    header('Location: requests.php' . (!empty($_POST['back']) ? '?' . ltrim($_POST['back'], '?') : ''));
    exit;
}
$flash = $_SESSION['flash_req'] ?? null;
unset($_SESSION['flash_req']);

/* ---------- Filters ---------- */
$fStatus = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$fType   = (int)($_GET['type'] ?? 0);
$fSearch = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = []; $args = [];
if ($fStatus !== '') { $where[] = 'dr.status = ?'; $args[] = $fStatus; }
if ($fType > 0)      { $where[] = 'dr.document_type_id = ?'; $args[] = $fType; }
if ($fSearch !== '') {
    $like = '%' . $fSearch . '%';
    $parts = ['dt.name LIKE ?']; $args[] = $like;
    if ($refCol)  { $parts[] = "dr.`$refCol` LIKE ?"; $args[] = $like; }
    if ($nameCol) { $parts[] = "u.`$nameCol` LIKE ?"; $args[] = $like; }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = "FROM document_requests dr
         LEFT JOIN users u ON dr.user_id = u.id
         LEFT JOIN document_types dt ON dr.document_type_id = dt.id";

$stmt = $pdo->prepare("SELECT COUNT(*) $from $whereSql");
$stmt->execute($args);
$found = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($found / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$sel = ["dr.id AS rid", "dr.status AS status", "dt.name AS document_name"];
$sel[] = $refCol  ? "dr.`$refCol` AS ref"      : "CONCAT('#', dr.id) AS ref";
$sel[] = $dateCol ? "dr.`$dateCol` AS made_on" : "NULL AS made_on";
$sel[] = $nameCol ? "u.`$nameCol` AS who"      : "CONCAT('User ', dr.user_id) AS who";
$order = $dateCol ? "dr.`$dateCol` DESC" : "dr.id DESC";

$stmt = $pdo->prepare("SELECT " . implode(', ', $sel) . " $from $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset");
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Tab counts and document type list */
$counts = $pdo->query("SELECT status, COUNT(*) c FROM document_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$allCount = array_sum($counts);
$types = $pdo->query("SELECT id, name FROM document_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$qs = function (array $over = []) use ($fStatus, $fType, $fSearch) {
    $p = array_merge(['status' => $fStatus, 'type' => $fType ?: '', 'q' => $fSearch], $over);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    return $p ? '?' . http_build_query($p) : 'requests.php';
};
$back = ltrim($qs(['page' => $page > 1 ? $page : '']), '?');
if ($back === 'requests.php') $back = '';

$title = 'Manage Requests';
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

.bd-flash { padding:12px 16px; border-radius:6px; margin-bottom:18px; font-weight:600; }
.bd-flash.ok { background:#d6f0e2; color:#14583a; } .bd-flash.err { background:#fde0de; color:#8a1f1a; }

.bd-tabs { display:flex; flex-wrap:wrap; gap:6px; border-bottom:2px solid var(--ink); }
.bd-tabs a { padding:9px 16px; text-decoration:none; color:var(--ink); font-weight:600; border-radius:6px 6px 0 0; }
.bd-tabs a span { color:var(--muted); font-weight:400; margin-left:4px; }
.bd-tabs a:hover { background:var(--mist); }
.bd-tabs a.on { background:var(--ink); color:#fff; } .bd-tabs a.on span { color:var(--sun); }
.bd-tabs a:focus-visible, .bd-bar-f :focus-visible, .bd-pg a:focus-visible { outline:3px solid var(--sun); outline-offset:2px; }

.bd-bar-f { display:flex; flex-wrap:wrap; gap:10px; padding:16px 0; }
.bd-bar-f input, .bd-bar-f select { border:2px solid var(--line); border-radius:6px; padding:8px 12px; font:inherit; color:var(--ink); background:#fff; }
.bd-bar-f input { flex:1 1 220px; }
.bd-btn { background:var(--ink); color:#fff; border:0; border-radius:6px; padding:9px 18px; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
.bd-btn:hover { background:#14408a; color:#fff; }
.bd-btn.ghost { background:transparent; color:var(--ink); border:2px solid var(--line); }
.bd-btn.sm { padding:6px 12px; font-size:.9rem; }

.bd-panel { border:2px solid var(--ink); border-radius:10px; padding:6px 20px 14px; background:#fff; overflow-x:auto; }
.bd-table { width:100%; border-collapse:collapse; font-size:.95rem; min-width:720px; }
.bd-table th { text-align:left; font-weight:600; color:var(--muted); padding:14px 10px 10px 0; border-bottom:2px solid var(--ink); }
.bd-table td { padding:12px 10px 12px 0; border-bottom:1px solid var(--line); vertical-align:middle; }
.bd-table tr:last-child td { border-bottom:0; }
.bd-ref { font-weight:600; }
.bd-tag { display:inline-block; padding:3px 10px; border-radius:4px; font-size:.85rem; font-weight:600; }
.bd-t-Pending{background:#fff1c2;color:#7a5a00} .bd-t-Processing{background:#dbe9ff;color:#0b3d91}
.bd-t-Completed{background:#d6f0e2;color:#14583a} .bd-t-x{background:var(--mist);color:var(--muted)}
.bd-upd { display:flex; gap:6px; margin:0; }
.bd-upd select { border:2px solid var(--line); border-radius:6px; padding:5px 8px; font:inherit; font-size:.9rem; }
.bd-empty { padding:36px 0; text-align:center; color:var(--muted); }

.bd-pg { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:18px; color:var(--muted); }
.bd-pg a, .bd-pg b { padding:6px 12px; border-radius:6px; text-decoration:none; border:2px solid var(--line); color:var(--ink); }
.bd-pg b { background:var(--ink); border-color:var(--ink); color:#fff; }
</style>

<div class="bd">
    <header class="bd-mast">
        <h1>Manage requests</h1>
        <p>Search, filter and update the status of resident document requests.</p>
    </header>

    <?php if ($flash): ?>
        <div class="bd-flash <?= $flash[0] ?>" role="status"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <nav class="bd-tabs" aria-label="Filter by status">
        <a href="<?= $qs(['status' => '', 'page' => '']) ?>" class="<?= $fStatus === '' ? 'on' : '' ?>">All <span><?= $allCount ?></span></a>
        <?php foreach ($statuses as $s): ?>
            <a href="<?= $qs(['status' => $s, 'page' => '']) ?>" class="<?= $fStatus === $s ? 'on' : '' ?>">
                <?= htmlspecialchars($s) ?> <span><?= (int)($counts[$s] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="bd-bar-f" method="get" action="requests.php">
        <?php if ($fStatus !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($fStatus) ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= htmlspecialchars($fSearch) ?>" placeholder="Search by reference, resident or document" aria-label="Search requests">
        <select name="type" aria-label="Document type">
            <option value="0">All documents</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= $fType === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="bd-btn" type="submit">Apply filters</button>
        <?php if ($fStatus !== '' || $fType || $fSearch !== ''): ?>
            <a class="bd-btn ghost" href="requests.php">Clear</a>
        <?php endif; ?>
    </form>

    <div class="bd-panel">
        <?php if (!$rows): ?>
            <p class="bd-empty">No requests match these filters. Try clearing them to see everything.</p>
        <?php else: ?>
        <table class="bd-table">
            <thead>
                <tr>
                    <th>Reference</th><th>Resident</th><th>Document</th>
                    <?php if ($dateCol): ?><th>Date</th><?php endif; ?>
                    <th>Status</th><th>Update</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $tag = in_array($r['status'], ['Pending','Processing','Completed'], true) ? $r['status'] : 'x'; ?>
                <tr>
                    <td class="bd-ref"><?= htmlspecialchars($r['ref']) ?></td>
                    <td><?= htmlspecialchars($r['who'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['document_name'] ?? '') ?></td>
                    <?php if ($dateCol): ?>
                        <td><?= $r['made_on'] ? date('M j, Y', strtotime($r['made_on'])) : '' ?></td>
                    <?php endif; ?>
                    <td><span class="bd-tag bd-t-<?= $tag ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td>
                        <form class="bd-upd" method="post" action="requests.php">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf_req'] ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['rid'] ?>">
                            <input type="hidden" name="back" value="<?= htmlspecialchars($back) ?>">
                            <select name="status" aria-label="New status for <?= htmlspecialchars($r['ref']) ?>">
                                <?php foreach ($statuses as $s): ?>
                                    <option <?= $s === $r['status'] ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="bd-btn sm" type="submit">Save</button>
                        </form>
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
        <?php if ($page > 1): ?><a href="<?= $qs(['page' => $page - 1]) ?>">Previous</a><?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
            <?= $i === $page ? "<b aria-current=\"page\">$i</b>" : '<a href="' . $qs(['page' => $i]) . "\">$i</a>" ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="<?= $qs(['page' => $page + 1]) ?>">Next</a><?php endif; ?>
    </nav>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>