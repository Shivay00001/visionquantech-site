<?php
// ============================================================================
// VisionQuantech API — shared configuration
// Plain PHP 8, no framework, no composer. Safe for cPanel shared hosting.
//
// DB credentials resolution order:
//   1. Environment variables: DB_HOST, DB_NAME, DB_USER, DB_PASS
//   2. private/db.php located OUTSIDE the web root (one level above
//      public_html). Copy private/db.php.example there and fill it in.
//      You can override the path with the VQ_DB_CONFIG_FILE env variable.
// ============================================================================
declare(strict_types=1);

/**
 * Resolve database credentials. Never echoes them.
 */
function vq_db_config(): array
{
    $cfg = [
        'host' => getenv('DB_HOST') ?: '',
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'pass' => getenv('DB_PASS') ?: '',
    ];

    if ($cfg['host'] === '' || $cfg['name'] === '') {
        $file = getenv('VQ_DB_CONFIG_FILE') ?: dirname(__DIR__) . '/private/db.php';
        if (is_file($file)) {
            $arr = require $file;
            if (is_array($arr)) {
                foreach (['host', 'name', 'user', 'pass'] as $k) {
                    if (isset($arr[$k]) && $arr[$k] !== '') {
                        $cfg[$k] = (string) $arr[$k];
                    }
                }
            }
        }
    }

    return $cfg;
}

/**
 * Shared PDO connection (lazy singleton, UTF-8, real prepared statements).
 *
 * @throws RuntimeException when the database is not configured.
 */
function vq_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = vq_db_config();
    if ($c['host'] === '' || $c['name'] === '' || $c['user'] === '') {
        throw new RuntimeException('Database not configured. See README.md deployment steps.');
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['name']);
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements
    ]);

    return $pdo;
}

/**
 * Send a JSON response and stop.
 */
function vq_json(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Best-effort client IP (shared hosting: REMOTE_ADDR is the reliable one).
 */
function vq_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * Basic file-based rate limiter: max $maxHits requests per $windowSeconds
 * per client IP, tracked in the OS temp directory (writable on shared hosts).
 * Returns true when the request is allowed, false when it must be rejected.
 */
function vq_rate_limit(string $bucket, int $maxHits, int $windowSeconds): bool
{
    $dir = rtrim(sys_get_temp_dir(), '/\\') . '/vq_ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $file = $dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $bucket)
        . '_' . md5(vq_client_ip()) . '.log';

    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $lines = explode("\n", (string) @file_get_contents($file));
        foreach ($lines as $line) {
            $t = (int) trim($line);
            if ($t > 0 && $t > $now - $windowSeconds) {
                $hits[] = $t;
            }
        }
    }

    if (count($hits) >= $maxHits) {
        return false;
    }

    $hits[] = $now;
    @file_put_contents($file, implode("\n", $hits), LOCK_EX);
    return true;
}
