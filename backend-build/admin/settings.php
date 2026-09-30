<?php
// ============================================================================
// Basic site settings (key/value). Only keys already present in the
// `settings` table can be edited — no arbitrary key creation from the form.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
vq_require_login();

$pdo = vq_pdo();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!vq_csrf_check($_POST['csrf'] ?? null)) {
        vq_flash_set('Security token mismatch — please try again.');
    } else {
        $allowed = $pdo->query('SELECT skey FROM settings')->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare('UPDATE settings SET svalue = :v WHERE skey = :k');
        $updated = 0;
        foreach ($allowed as $key) {
            if (array_key_exists($key, $_POST)) {
                $stmt->execute([':v' => trim((string) $_POST[$key]), ':k' => $key]);
                $updated++;
            }
        }
        vq_flash_set('Settings saved (' . $updated . ' updated).');
    }
    header('Location: settings.php');
    exit;
}

$rows = $pdo->query('SELECT skey, svalue FROM settings ORDER BY skey')->fetchAll();

$labels = [
    'site_name'       => 'Site name',
    'contact_email'   => 'Contact email (shown on site)',
    'whatsapp_number' => 'WhatsApp number (e.g. +91XXXXXXXXXX)',
    'announcement'    => 'Announcement banner (blank = hidden)',
];

vq_head('Settings', 'settings');
vq_flash_show();
?>
<div class="card">
  <form method="post" action="settings.php">
    <?= vq_csrf_field() ?>
    <?php foreach ($rows as $r): ?>
      <label for="s_<?= e($r['skey']) ?>"><?= e($labels[$r['skey']] ?? $r['skey']) ?></label>
      <input type="text" id="s_<?= e($r['skey']) ?>" name="<?= e($r['skey']) ?>"
             value="<?= e((string) $r['svalue']) ?>">
    <?php endforeach; ?>
    <p style="margin-top:16px"><button class="btn" type="submit">Save settings</button></p>
  </form>
  <p class="muted">These values are read by the website via a future <code>/api/settings.php</code>
     (not included — add it only if the frontend needs live settings).</p>
</div>
<?php vq_foot(); ?>
