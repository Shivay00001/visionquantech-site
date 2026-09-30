<?php
// ============================================================================
// GET /api/posts.php — published research posts / updates for the website
//   /api/posts.php                      → list (newest first)
//   /api/posts.php?category=research     → filtered list
//   /api/posts.php?slug=my-post         → single post
//   ?limit=N  (1–50, default 20)         → pagination size for lists
// Drafts are never exposed here.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    vq_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$slug     = trim((string) ($_GET['slug'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$limit    = (int) ($_GET['limit'] ?? 20);
if ($limit < 1 || $limit > 50) {
    $limit = 20;
}

try {
    $pdo = vq_pdo();

    if ($slug !== '') {
        // --- Single published post ------------------------------------------
        $stmt = $pdo->prepare(
            "SELECT slug, title, body, category, published_at
             FROM posts
             WHERE slug = :slug AND status = 'published'
             LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $post = $stmt->fetch();

        if (!$post) {
            vq_json(['ok' => false, 'error' => 'Post not found.'], 404);
        }
        vq_json(['ok' => true, 'post' => $post]);
    }

    // --- List of published posts ---------------------------------------------
    $where  = "WHERE status = 'published'";
    $params = [];
    if ($category === 'research' || $category === 'updates') {
        $where .= ' AND category = :category';
        $params[':category'] = $category;
    }

    // $limit is an int we clamped above — safe to interpolate.
    $stmt = $pdo->prepare(
        "SELECT slug, title, category,
                LEFT(body, 300) AS excerpt, published_at
         FROM posts
         $where
         ORDER BY published_at DESC, id DESC
         LIMIT $limit"
    );
    $stmt->execute($params);

    vq_json(['ok' => true, 'posts' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('[vq] posts.php failed: ' . $e->getMessage());
    vq_json(['ok' => false, 'error' => 'Could not load posts.'], 500);
}
