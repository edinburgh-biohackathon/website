<?php
declare(strict_types=1);

// Shared helpers for /api. Errors returned to clients stay generic; details go to the Apache error log without personal data.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function config(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    // Preferred: a private dir outside the docroot (once the host provides one). Fallback: lib/config.php (denied by .htaccess).
    $paths = [getenv('BIOHACK_CONFIG') ?: '', '/var/www-private/biohack/config.php', __DIR__ . '/config.php'];
    foreach ($paths as $p) {
        if ($p !== '' && is_readable($p)) { $cfg = require $p; return $cfg; }
    }
    throw new RuntimeException('config missing');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = config();
        $pdo = new PDO($c['db_dsn'], $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function json_out(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $code): never {
    json_out($status, ['error' => $code]);
}

// Write requests must be JSON and same-origin. Cross-site pages can't send application/json without a CORS preflight, which we never answer.
function require_json_same_origin(): void {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== 0) fail(415, 'unsupported_media_type');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin !== config()['site_origin']) fail(403, 'forbidden');
}

function read_json_body(int $maxBytes = 65536): array {
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || strlen($raw) > $maxBytes) fail(413, 'too_large');
    $data = json_decode($raw, true);
    if (!is_array($data)) fail(400, 'bad_json');
    return $data;
}

function now_utc(): string {
    return gmdate('Y-m-d H:i:s');
}

function client_key(): string {
    return hash('sha256', config()['ip_salt'] . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

// Allow at most $max events per $windowSec for this client in $bucket; records the attempt. 429 if exceeded.
function rate_limit(string $bucket, int $max, int $windowSec): void {
    $pdo = db();
    $key = client_key();
    $pdo->prepare('DELETE FROM rate_limits WHERE created_at < UTC_TIMESTAMP() - INTERVAL 1 DAY')->execute();
    $q = $pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND key_hash = ? AND created_at > UTC_TIMESTAMP() - INTERVAL ? SECOND');
    $q->execute([$bucket, $key, $windowSec]);
    if ((int)$q->fetchColumn() >= $max) fail(429, 'rate_limited');
    $pdo->prepare('INSERT INTO rate_limits (bucket, key_hash, created_at) VALUES (?, ?, ?)')->execute([$bucket, $key, now_utc()]);
}

function header_safe(string $s): string {
    return trim(str_replace(["\r", "\n", "\0"], ' ', $s));
}

// Plain-text UTF-8 email. Returns false on failure (caller decides; we never fail a request over email).
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool {
    $c = config();
    $headers = [
        'From: ' . header_safe($c['mail_from']),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($replyTo) $headers[] = 'Reply-To: ' . header_safe($replyTo);
    $subj = mb_encode_mimeheader(header_safe($subject), 'UTF-8', 'Q');
    // Envelope sender, so bounces go to the committee inbox instead of www-data.
    $ok = mail(header_safe($to), $subj, $body, implode("\r\n", $headers), '-f' . $c['reply_to']);
    if (!$ok) error_log('biohack api: mail() failed');
    return $ok;
}
