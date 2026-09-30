<?php
// ============================================================================
// Admin authentication + CSRF helpers (session based).
// Passwords are verified with password_verify() against the `admins` table.
// ============================================================================
declare(strict_types=1);

/** Currently logged-in admin, or null. */
function vq_admin(): ?array
{
    $a = $_SESSION['vq_admin'] ?? null;
    return is_array($a) ? $a : null;
}

/** Redirect to login.php when nobody is logged in. */
function vq_require_login(): void
{
    if (vq_admin() === null) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Attempt login. Returns true on success (session is populated).
 * Deliberately generic — no user enumeration via timing differences here
 * is out of scope for a small panel, but the error message stays generic.
 */
function vq_login(string $username, string $password): bool
{
    try {
        $pdo = vq_pdo();
        $stmt = $pdo->prepare(
            'SELECT id, username, password_hash FROM admins WHERE username = :u LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('[vq] admin login db error: ' . $e->getMessage());
        return false;
    }

    if ($row && password_verify($password, (string) $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['vq_admin'] = [
            'id'       => (int) $row['id'],
            'username' => (string) $row['username'],
        ];
        return true;
    }
    return false;
}

/** Log out and forget the session. */
function vq_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Get (creating if needed) the CSRF token for this session. */
function vq_csrf_token(): string
{
    if (empty($_SESSION['vq_csrf'])) {
        $_SESSION['vq_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['vq_csrf'];
}

/** Hidden input field carrying the CSRF token. Echo inside every POST form. */
function vq_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="'
        . htmlspecialchars(vq_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Verify a submitted CSRF token. */
function vq_csrf_check(?string $submitted): bool
{
    $expected = $_SESSION['vq_csrf'] ?? '';
    return $expected !== '' && $submitted !== null
        && hash_equals((string) $expected, (string) $submitted);
}
