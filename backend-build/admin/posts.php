<?php
// ============================================================================
// Posts list: research papers / updates. New / edit / delete.
// Delete is a CSRF-protected POST handled here.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
vq_require_login();

$pdo = vq_pdo();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!vq_csrf_check($_POST['csrf'] ?? null)) {
        vq_flash_set('Security token mismatch — please try again.');
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'delete' && $id > 0) {
            $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $stmt->execute([':id' => $id]);
            vq_flash_set('Post #' . $id . ' deleted.');
        }
    }
    header('Location: posts.php');
    exit;
}

$posts = $pdo->query(
    'SELECT id, slug, title, category, status, published_at, updated_at
     FROM posts
     ORDER BY id DESC'
)->fetchAll();

vq_head('Posts', 'posts');
vq_flash_show();
?>
<div class="card">
  <div class="toolbar">
    <span class="muted">Research papers & site updates. Published posts appear on the website via <code>/api/posts.php</code>.</span>
    <span class="spacer"></span>
    <a class="btn btn-sm" href="post_edit.php">+ New post</a>
  </div>
  <table class="data">
    <thead><tr><th>Title</th><th>Slug</th><th>Category</th><th>Status</th><th>Published</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><strong><?= e($p['title']) ?></strong></td>
        <td class="muted">/<?= e($p['slug']) ?></td>
        <td><?= e($p['category']) ?></td>
        <td><span class="<?= vq_status_class($p['status']) ?>"><?= e($p['status']) ?></span></td>
        <td class="muted"><?= $p['published_at'] ? e(date('d M Y', strtotime($p['published_at']))) : '—' ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="post_edit.php?id=<?= (int) $p['id'] ?>">Edit</a>
          <form class="inline" method="post" action="posts.php"
                onsubmit="return confirm('Delete “<?= e($p['title']) ?>”? This cannot be undone.');">
            <?= vq_csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if ($posts === []): ?>
      <tr><td colspan="6" class="muted">No posts yet. Click “New post” to write your first research update.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php vq_foot(); ?>
