<?php
// ============================================================================
// Post editor: create + update. Slug auto-generated from title when empty.
// Body accepts HTML (or Markdown — the frontend decides how to render it).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
vq_require_login();

$pdo = vq_pdo();
$id  = (int) ($_GET['id'] ?? 0);

// --- Save --------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!vq_csrf_check($_POST['csrf'] ?? null)) {
        vq_flash_set('Security token mismatch — please try again.');
        header('Location: post_edit.php' . ($id > 0 ? '?id=' . $id : ''));
        exit;
    }

    $id       = (int) ($_POST['id'] ?? 0);
    $title    = trim((string) ($_POST['title'] ?? ''));
    $slug     = trim((string) ($_POST['slug'] ?? ''));
    $category = (string) ($_POST['category'] ?? 'updates');
    $status   = (string) ($_POST['status'] ?? 'draft');
    $body     = (string) ($_POST['body'] ?? '');
    $pubRaw   = trim((string) ($_POST['published_at'] ?? ''));

    $errors = [];
    if ($title === '' || mb_strlen($title) > 255) {
        $errors[] = 'Title is required (max 255 characters).';
    }
    if ($slug === '') {
        // Auto-slug from title: lowercase, non-alphanumerics → dashes.
        $slug = trim(preg_replace('/[^a-z0-9]+/i', '-', strtolower($title)), '-');
    }
    if (!preg_match('/^[a-z0-9-]{1,190}$/i', $slug)) {
        $errors[] = 'Slug may only contain letters, numbers and dashes.';
    }
    if ($category !== 'research' && $category !== 'updates') {
        $errors[] = 'Invalid category.';
    }
    if ($status !== 'draft' && $status !== 'published') {
        $errors[] = 'Invalid status.';
    }
    if ($body === '') {
        $errors[] = 'Body cannot be empty.';
    }

    // published_at: accept "YYYY-MM-DDTHH:MM" from datetime-local, or blank.
    $publishedAt = null;
    if ($pubRaw !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $pubRaw);
        if ($dt === false) {
            $errors[] = 'Invalid publish date.';
        } else {
            $publishedAt = $dt->format('Y-m-d H:i:s');
        }
    } elseif ($status === 'published') {
        $publishedAt = date('Y-m-d H:i:s');
    }

    // Slug must be unique (excluding the row being edited).
    if ($errors === []) {
        $chk = $pdo->prepare('SELECT id FROM posts WHERE slug = :slug AND id <> :id LIMIT 1');
        $chk->execute([':slug' => $slug, ':id' => $id]);
        if ($chk->fetch()) {
            $errors[] = 'That slug is already used by another post.';
        }
    }

    if ($errors === []) {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE posts
                 SET slug = :slug, title = :title, body = :body, category = :category,
                     status = :status, published_at = :published_at
                 WHERE id = :id'
            );
            $stmt->execute([
                ':slug' => $slug, ':title' => $title, ':body' => $body,
                ':category' => $category, ':status' => $status,
                ':published_at' => $publishedAt, ':id' => $id,
            ]);
            vq_flash_set('Post updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO posts (slug, title, body, category, status, published_at)
                 VALUES (:slug, :title, :body, :category, :status, :published_at)'
            );
            $stmt->execute([
                ':slug' => $slug, ':title' => $title, ':body' => $body,
                ':category' => $category, ':status' => $status,
                ':published_at' => $publishedAt,
            ]);
            $id = (int) $pdo->lastInsertId();
            vq_flash_set('Post created.');
        }
        header('Location: post_edit.php?id=' . $id);
        exit;
    }

    // Validation failed: redisplay the form with submitted values.
    $post = [
        'id' => $id, 'slug' => $slug, 'title' => $title, 'body' => $body,
        'category' => $category, 'status' => $status,
        'published_at' => $publishedAt,
    ];
} elseif ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $post = $stmt->fetch();
    if (!$post) {
        vq_flash_set('Post not found.');
        header('Location: posts.php');
        exit;
    }
    $errors = [];
} else {
    $post = [
        'id' => 0, 'slug' => '', 'title' => '', 'body' => '',
        'category' => 'updates', 'status' => 'draft', 'published_at' => null,
    ];
    $errors = [];
}

// datetime-local wants "YYYY-MM-DDTHH:MM".
$pubValue = '';
if (!empty($post['published_at'])) {
    $pubValue = date('Y-m-d\TH:i', strtotime($post['published_at']));
}

vq_head($id > 0 ? 'Edit post' : 'New post', 'posts');
vq_flash_show();
if ($errors !== []) {
    echo '<div class="error"><ul>';
    foreach ($errors as $er) {
        echo '<li>' . e($er) . '</li>';
    }
    echo '</ul></div>';
}
?>
<div class="card">
  <form method="post" action="post_edit.php<?= $id > 0 ? '?id=' . $id : '' ?>">
    <?= vq_csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">

    <label for="title">Title</label>
    <input type="text" id="title" name="title" required maxlength="255"
           value="<?= e($post['title']) ?>">

    <div class="form-row">
      <div>
        <label for="slug">Slug (leave empty to auto-generate)</label>
        <input type="text" id="slug" name="slug" maxlength="190"
               value="<?= e($post['slug']) ?>" placeholder="my-research-post">
      </div>
      <div>
        <label for="published_at">Publish date (blank = now when publishing)</label>
        <input type="datetime-local" id="published_at" name="published_at" value="<?= e($pubValue) ?>">
      </div>
    </div>

    <div class="form-row">
      <div>
        <label for="category">Category</label>
        <select id="category" name="category">
          <option value="research" <?= $post['category'] === 'research' ? 'selected' : '' ?>>research</option>
          <option value="updates" <?= $post['category'] === 'updates' ? 'selected' : '' ?>>updates</option>
        </select>
      </div>
      <div>
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>draft</option>
          <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>published</option>
        </select>
      </div>
    </div>

    <label for="body">Body (HTML allowed)</label>
    <textarea id="body" name="body" required><?= e($post['body']) ?></textarea>

    <p style="margin-top:16px">
      <button class="btn" type="submit">Save post</button>
      <a class="btn btn-ghost" href="posts.php">Cancel</a>
    </p>
  </form>
</div>
<?php vq_foot(); ?>
