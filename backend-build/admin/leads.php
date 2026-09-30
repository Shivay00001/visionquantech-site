<?php
// ============================================================================
// Leads management: filter/search list, status updates, delete.
// POST actions (CSRF protected): update_status, delete.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
vq_require_login();

$pdo = vq_pdo();
$statuses = ['new', 'contacted', 'qualified', 'won', 'lost'];

// --- Handle POST actions ------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!vq_csrf_check($_POST['csrf'] ?? null)) {
        vq_flash_set('Security token mismatch — please try again.');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $id     = (int) ($_POST['id'] ?? 0);

        if ($action === 'update_status' && $id > 0) {
            $status = (string) ($_POST['status'] ?? '');
            if (in_array($status, $statuses, true)) {
                $stmt = $pdo->prepare('UPDATE leads SET status = :s WHERE id = :id');
                $stmt->execute([':s' => $status, ':id' => $id]);
                vq_flash_set('Lead #' . $id . ' marked as ' . $status . '.');
            }
        } elseif ($action === 'delete' && $id > 0) {
            $stmt = $pdo->prepare('DELETE FROM leads WHERE id = :id');
            $stmt->execute([':id' => $id]);
            vq_flash_set('Lead #' . $id . ' deleted.');
        }
    }
    $back = (string) ($_POST['return'] ?? 'leads.php');
    // Only allow relative redirects within this panel.
    if (!preg_match('#^[a-z0-9_.-]+\.php(\?.*)?$#i', $back)) {
        $back = 'leads.php';
    }
    header('Location: ' . $back);
    exit;
}

// --- Filters ------------------------------------------------------------------
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, array_merge($statuses, ['all']), true)) {
    $statusFilter = 'all';
}
$q    = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($statusFilter !== 'all') {
    $where[] = 'status = :status';
    $params[':status'] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(name LIKE :q OR email LIKE :q OR phone LIKE :q OR message LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT id, name, email, phone, business_type, message, source_page, status, created_at
     FROM leads $whereSql
     ORDER BY id DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));
$qs = http_build_query(['status' => $statusFilter, 'q' => $q]);

vq_head('Leads', 'leads');
vq_flash_show();
?>

<div class="card">
  <form class="toolbar" method="get" action="leads.php">
    <select class="inline-sm" name="status" onchange="this.form.submit()">
      <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
      <?php foreach ($statuses as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input class="inline-sm" type="text" name="q" value="<?= e($q) ?>" placeholder="Search name, email, message…">
    <button class="btn btn-sm" type="submit">Search</button>
    <span class="spacer"></span>
    <span class="muted"><?= $total ?> lead<?= $total === 1 ? '' : 's' ?></span>
  </form>

  <table class="data">
    <thead>
      <tr><th>#</th><th>Name</th><th>Contact</th><th>Type</th><th>Message</th><th>Status</th><th>Received</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($leads as $r): ?>
      <tr>
        <td class="muted"><?= (int) $r['id'] ?></td>
        <td><strong><?= e($r['name']) ?></strong></td>
        <td><?= e($r['email']) ?><?= $r['phone'] ? '<br>' . e($r['phone']) : '' ?></td>
        <td><?= e($r['business_type'] ?? '—') ?></td>
        <td class="msg-cell" title="<?= e($r['message']) ?>"><?= e(mb_strimwidth($r['message'], 0, 120, '…')) ?></td>
        <td>
          <form class="inline" method="post" action="leads.php">
            <?= vq_csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="return" value="leads.php?<?= e($qs) ?>&page=<?= $page ?>">
            <select class="inline-sm" name="status" onchange="this.form.submit()">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td class="muted"><?= e(date('d M Y, H:i', strtotime($r['created_at']))) ?><br><?= e($r['source_page'] ?? '') ?></td>
        <td>
          <form class="inline" method="post" action="leads.php"
                onsubmit="return confirm('Delete lead #<?= (int) $r['id'] ?>? This cannot be undone.');">
            <?= vq_csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($leads === []): ?>
      <tr><td colspan="8" class="muted">No leads match this filter.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
  <p class="muted">
    Page <?= $page ?> of <?= $totalPages ?> ·
    <?php if ($page > 1): ?><a href="leads.php?<?= e($qs) ?>&page=<?= $page - 1 ?>">← Prev</a><?php endif; ?>
    <?php if ($page < $totalPages): ?><a href="leads.php?<?= e($qs) ?>&page=<?= $page + 1 ?>">Next →</a><?php endif; ?>
  </p>
  <?php endif; ?>
</div>
<?php vq_foot(); ?>
