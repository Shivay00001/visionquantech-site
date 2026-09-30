<?php
// ============================================================================
// POST /api/track.php — lightweight page-view analytics
// Body (JSON or form): { "path": "/solutions" }
// Rate limit: 30 hits / minute / IP (generous; page loads only).
// Writes into `page_views`. Failures are silent by design (never break UX).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    vq_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$in = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $in = $decoded;
    }
}

$path = trim((string) ($in['path'] ?? ''));
if ($path === '' || mb_strlen($path) > 255 || $path[0] !== '/') {
    vq_json(['ok' => true]); // ignore junk quietly
}

if (!vq_rate_limit('track', 30, 60)) {
    vq_json(['ok' => true]); // over limit: drop silently
}

try {
    $pdo = vq_pdo();
    $stmt = $pdo->prepare('INSERT INTO page_views (path) VALUES (:path)');
    $stmt->execute([':path' => $path]);
} catch (Throwable $e) {
    error_log('[vq] track.php failed: ' . $e->getMessage());
}

vq_json(['ok' => true]);
