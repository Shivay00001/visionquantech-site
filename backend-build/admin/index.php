<?php
// ============================================================================
// Dashboard: lead stats, 14-day charts (Chart.js CDN), recent enquiries.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
vq_require_login();

$pdo = vq_pdo();

// --- Headline stats ----------------------------------------------------------
$totalLeads = (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
$newLeads   = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
$weekLeads  = (int) $pdo->query('SELECT COUNT(*) FROM leads WHERE created_at >= NOW() - INTERVAL 7 DAY')->fetchColumn();
$weekViews  = (int) $pdo->query('SELECT COUNT(*) FROM page_views WHERE viewed_at >= NOW() - INTERVAL 7 DAY')->fetchColumn();

// --- Per-day series for the last 14 days (fill gaps with 0) -------------------
$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days[$d] = 0;
}

$leadsByDay = $days;
$stmt = $pdo->query(
    'SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM leads
     WHERE created_at >= CURDATE() - INTERVAL 13 DAY
     GROUP BY d'
);
foreach ($stmt->fetchAll() as $row) {
    $leadsByDay[$row['d']] = (int) $row['c'];
}

$viewsByDay = $days;
$stmt = $pdo->query(
    'SELECT DATE(viewed_at) AS d, COUNT(*) AS c
     FROM page_views
     WHERE viewed_at >= CURDATE() - INTERVAL 13 DAY
     GROUP BY d'
);
foreach ($stmt->fetchAll() as $row) {
    $viewsByDay[$row['d']] = (int) $row['c'];
}

$labels = array_keys($days);

// --- Recent enquiries ----------------------------------------------------------
$recent = $pdo->query(
    'SELECT id, name, email, phone, business_type, message, status, created_at
     FROM leads
     ORDER BY id DESC
     LIMIT 10'
)->fetchAll();

$statuses = ['new', 'contacted', 'qualified', 'won', 'lost'];

vq_head('Dashboard', 'dashboard');
vq_flash_show();
?>

<div class="stats">
  <div class="stat"><div class="num"><?= $totalLeads ?></div><div class="lbl">Total leads</div></div>
  <div class="stat"><div class="num"><?= $newLeads ?></div><div class="lbl">New (unread)</div></div>
  <div class="stat"><div class="num"><?= $weekLeads ?></div><div class="lbl">Leads · last 7 days</div></div>
  <div class="stat"><div class="num"><?= $weekViews ?></div><div class="lbl">Page views · last 7 days</div></div>
</div>

<div class="card">
  <h2>Leads per day — last 14 days</h2>
  <div class="chart-box"><canvas id="leadsChart"></canvas></div>
</div>

<div class="card">
  <h2>Page views per day — last 14 days</h2>
  <div class="chart-box"><canvas id="viewsChart"></canvas></div>
</div>

<div class="card">
  <h2>Recent enquiries</h2>
  <table class="data">
    <thead>
      <tr><th>Name</th><th>Contact</th><th>Type</th><th>Message</th><th>Status</th><th>Received</th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['email']) ?><?= $r['phone'] ? '<br>' . e($r['phone']) : '' ?></td>
        <td><?= e($r['business_type'] ?? '—') ?></td>
        <td class="msg-cell" title="<?= e($r['message']) ?>"><?= e(mb_strimwidth($r['message'], 0, 90, '…')) ?></td>
        <td>
          <form class="inline" method="post" action="leads.php">
            <?= vq_csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="return" value="index.php">
            <select class="inline-sm" name="status" onchange="this.form.submit()">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td class="muted"><?= e(date('d M, H:i', strtotime($r['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if ($recent === []): ?>
      <tr><td colspan="6" class="muted">No enquiries yet. New contact-form submissions will appear here.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <p><a class="btn btn-ghost btn-sm" href="leads.php">Manage all leads →</a></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
new Chart(document.getElementById('leadsChart'), {
  type: 'line',
  data: { labels, datasets: [{ label: 'Leads', data: <?= json_encode(array_values($leadsByDay)) ?>, tension: 0.3, fill: true }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
             scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
new Chart(document.getElementById('viewsChart'), {
  type: 'bar',
  data: { labels, datasets: [{ label: 'Views', data: <?= json_encode(array_values($viewsByDay)) ?> }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
             scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>
<?php vq_foot(); ?>
