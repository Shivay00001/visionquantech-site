<?php
// ============================================================================
// Admin layout helpers: page head/nav/footer + HTML escaping.
// No build step — plain PHP templates + one stylesheet.
// ============================================================================
declare(strict_types=1);

/** Escape for HTML output. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Status → CSS badge class. */
function vq_status_class(string $status): string
{
    return match ($status) {
        'new'       => 'badge badge-new',
        'contacted' => 'badge badge-contacted',
        'qualified' => 'badge badge-qualified',
        'won'       => 'badge badge-won',
        'lost'      => 'badge badge-lost',
        'published' => 'badge badge-won',
        'draft'     => 'badge badge-new',
        default     => 'badge',
    };
}

/**
 * Open the page: <head>, top bar and nav. $active is one of
 * dashboard|leads|posts|settings ('' for the login page — hides the nav).
 */
function vq_head(string $title, string $active = ''): void
{
    $admin = vq_admin();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · VisionQuantech Admin</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <span class="brand">VisionQuantech <em>admin</em></span>
    <?php if ($admin !== null): ?>
    <nav class="nav">
      <a href="index.php"    class="<?= $active === 'dashboard' ? 'on' : '' ?>">Dashboard</a>
      <a href="leads.php"    class="<?= $active === 'leads' ? 'on' : '' ?>">Leads</a>
      <a href="posts.php"    class="<?= $active === 'posts' ? 'on' : '' ?>">Posts</a>
      <a href="settings.php" class="<?= $active === 'settings' ? 'on' : '' ?>">Settings</a>
    </nav>
    <span class="user"><?= e($admin['username']) ?> · <a href="logout.php">Logout</a></span>
    <?php endif; ?>
  </div>
</header>
<main class="wrap">
  <h1 class="page-title"><?= e($title) ?></h1>
    <?php
}

/** Close the page. */
function vq_foot(): void
{
    ?>
</main>
<footer class="footer">VisionQuantech admin · keep this URL private</footer>
</body>
</html>
    <?php
}

/** Flash message helper (stored in session, shown once). */
function vq_flash_set(string $msg): void
{
    $_SESSION['vq_flash'] = $msg;
}

function vq_flash_show(): void
{
    if (!empty($_SESSION['vq_flash'])) {
        echo '<div class="flash">' . e((string) $_SESSION['vq_flash']) . '</div>';
        unset($_SESSION['vq_flash']);
    }
}
