<?php
declare(strict_types=1);

/* ------------------------------------------------------------------
 * Bootstrap bersama untuk halaman dan API: konfigurasi, sesi, database,
 * CSRF, helper JSON, dan helper validasi.
 * ------------------------------------------------------------------ */

function cfg(string $key): mixed
{
    static $config = null;
    $config ??= require __DIR__ . '/config.php';
    return $config[$key] ?? null;
}

date_default_timezone_set((string) cfg('timezone'));
mb_internal_encoding('UTF-8');

/** @return array{expense: array<string, array{emoji:string,color:string}>, income: array<string, array{emoji:string,color:string}>} */
function categories(): array
{
    static $cats = null;
    return $cats ??= require __DIR__ . '/categories.php';
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ------------------------------- Sesi & header ------------------------------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name((string) cfg('session_name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function current_user_id(): ?int
{
    return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);          // cegah session fixation
    $_SESSION['uid']  = $userId;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 3600,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/* -------------------------------- Database -------------------------------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db_host'), (int) cfg('db_port'), cfg('db_name'));
    $pdo = new PDO($dsn, (string) cfg('db_user'), (string) cfg('db_pass'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

/* ------------------------------- Helper API ------------------------------- */

function api_init(): void
{
    ini_set('display_errors', '0');
    start_session();
    security_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    set_exception_handler(function (Throwable $t): void {
        error_log('[CatatKan] ' . $t);
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo json_encode(['ok' => false, 'message' => 'Terjadi kesalahan pada server. Coba lagi nanti.', 'errors' => new stdClass()]);
    });
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data + ['ok' => $status < 400], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function json_fail(string $message, int $status = 400, array $errors = []): never
{
    json_out(['message' => $message, 'errors' => (object) $errors], $status);
}

function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_fail('Format permintaan tidak valid.', 400);
    }
    return $data;
}

function verify_csrf(): void
{
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $real = (string) ($_SESSION['csrf'] ?? '');
    if ($real === '' || !hash_equals($real, $sent)) {
        json_fail('Sesi tidak valid atau kedaluwarsa. Muat ulang halaman.', 419);
    }
}

/** Wajib POST + token CSRF valid; mengembalikan body JSON. */
function require_post(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_fail('Metode tidak diizinkan.', 405);
    }
    verify_csrf();
    return request_body();
}

function require_auth(): int
{
    $uid = current_user_id();
    if ($uid === null) {
        json_fail('Silakan masuk terlebih dahulu.', 401);
    }
    return $uid;
}

/* ------------------------------ Helper validasi ------------------------------ */

function int_in(array $in, string $key): ?int
{
    $v = $in[$key] ?? null;
    if (is_int($v)) {
        return $v;
    }
    if (is_string($v) && preg_match('/^\d{1,15}$/', $v)) {
        return (int) $v;
    }
    if (is_float($v) && $v >= 0 && $v < 1e15 && floor($v) === $v) {
        return (int) $v;
    }
    return null;
}

function clean_text(mixed $value): string
{
    return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
}

/* ------------------------------ Helper format ------------------------------ */

function rp(int $n): string
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function month_short(int $m): string
{
    return ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$m - 1];
}

function month_long(int $m): string
{
    return ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][$m - 1];
}

/** Bentuk baris transaksi untuk respons JSON. */
function tx_row(array $r): array
{
    $meta = categories()[$r['type']][$r['category']] ?? ['emoji' => '•', 'color' => '#93a09a'];
    return [
        'id'       => (int) $r['id'],
        'name'     => (string) $r['name'],
        'amount'   => (int) $r['amount'],
        'type'     => (string) $r['type'],
        'category' => (string) $r['category'],
        'date'     => (string) $r['tx_date'],
        'note'     => $r['note'] !== null ? (string) $r['note'] : '',
        'emoji'    => $meta['emoji'],
        'color'    => $meta['color'],
    ];
}
