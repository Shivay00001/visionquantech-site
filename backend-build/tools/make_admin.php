<?php
// ============================================================================
// make_admin.php — ONE-TIME admin user creator. DELETE AFTER USE.
//
// Usage (preferred):  php make_admin.php <username>
//   Run from the backend-build/ directory on any machine with PHP 8+ and
//   network access to the MySQL server, with DB credentials available as
//   environment variables (see api/config.php), e.g.:
//
//     DB_HOST=localhost DB_NAME=visionquantech DB_USER=... DB_PASS=... \
//       php tools/make_admin.php shivay
//
//   You will be prompted for the password (not echoed).
//
// No SSH on the shared host? Alternative: temporarily upload this file into
// admin/tools/, set $ALLOW_WEB_RUN = true below, open it ONCE in the browser,
// create the user, then DELETE the file immediately. Leaving it on the
// server is a critical security hole.
// ============================================================================
declare(strict_types=1);

$ALLOW_WEB_RUN = false; // set true ONLY for the one-time browser run, then delete the file

$isCli = php_sapi_name() === 'cli';
if (!$isCli && !$ALLOW_WEB_RUN) {
    http_response_code(403);
    exit('Forbidden. This tool runs from the command line only.');
}

require __DIR__ . '/../api/config.php';

function out(string $msg): void
{
    global $isCli;
    echo $isCli ? $msg . PHP_EOL : '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
}

function prompt_password(string $label): string
{
    global $isCli;
    if (!$isCli) {
        return (string) ($_POST['password'] ?? '');
    }
    // Hide typed password on POSIX terminals.
    if (DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        out($label . ' (hidden): ');
        @shell_exec('stty -echo');
        $pw = (string) fgets(STDIN);
        @shell_exec('stty echo');
        out('');
        return rtrim($pw, "\r\n");
    }
    out($label . ': ');
    return rtrim((string) fgets(STDIN), "\r\n");
}

// --- Web-runner form ----------------------------------------------------------
if (!$isCli) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:420px;margin:60px auto">'
            . '<h2>Create admin user (ONE-TIME)</h2>'
            . '<p style="color:#b91c1c"><strong>Delete this file immediately after use.</strong></p>'
            . '<form method="post">'
            . '<p>Username<br><input name="username" required></p>'
            . '<p>Password<br><input type="password" name="password" required></p>'
            . '<p><button type="submit">Create</button></p>'
            . '</form></body></html>';
        exit;
    }
    $username = trim((string) ($_POST['username'] ?? ''));
} else {
    $username = trim((string) ($argv[1] ?? ''));
    if ($username === '') {
        out('Usage: php make_admin.php <username>');
        exit(1);
    }
}

if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) {
    out('Invalid username (3-60 chars: letters, numbers, _ . -).');
    exit(1);
}

$password = prompt_password('Password for "' . $username . '"');
if (strlen($password) < 12) {
    out('Password must be at least 12 characters.');
    exit(1);
}

try {
    $pdo = vq_pdo();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO admins (username, password_hash) VALUES (:u, :h)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    );
    $stmt->execute([':u' => $username, ':h' => $hash]);
    out('OK: admin user "' . $username . '" created/updated. DELETE THIS FILE NOW.');
} catch (Throwable $e) {
    out('FAILED: ' . $e->getMessage());
    exit(1);
}
