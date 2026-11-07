<?php
/* ভোটার তালিকা সাইট — কমন ফাইল (সেশন, ডাটাবেস, সহায়ক ফাংশন) */
declare(strict_types=1);
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Dhaka');

const CONFIG_FILE = __DIR__ . '/config.php';

function is_installed(): bool { return is_file(CONFIG_FILE); }

function cfg(string $k, $def = null) {
    static $c = null;
    if ($c === null) $c = is_installed() ? (require CONFIG_FILE) : [];
    return $c[$k] ?? $def;
}

function is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('MCSESS');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '28800');
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . cfg('db_host', 'localhost') . ';dbname=' . cfg('db_name') . ';charset=utf8mb4';
    if (cfg('db_port')) $dsn .= ';port=' . (int)cfg('db_port');
    $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone='+06:00'");
    return $pdo;
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

const BN_DIGITS = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
function bn($n): string { return str_replace(range(0, 9), BN_DIGITS, (string)$n); }
function en_digits(string $s): string { return str_replace(BN_DIGITS, range(0, 9), $s); }

/* অনুসন্ধানের জন্য লেখা স্বাভাবিক করা — মূল HTML এর nz() এর মতো */
function nz(?string $s): string {
    $s = (string)$s;
    if (class_exists('Normalizer')) { $t = Normalizer::normalize($s, Normalizer::FORM_C); if ($t !== false) $s = $t; }
    $s = en_digits($s);
    $s = preg_replace('/[\s\x{200C}\x{200D}]+/u', '', $s);
    $s = str_replace(['-', '.'], '/', $s);
    return mb_strtolower($s);
}

/* বাংলা dd/mm/yyyy → Y-m-d */
function parse_dob(string $dob): ?string {
    if (!preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', nz($dob), $m)) return null;
    [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
}

/* ---------- সেটিংস ---------- */
const DEFAULT_SETTINGS = [
    'site_title' => 'ভোটের দিনের জন্য অনুমোদিত',
    'subtitle'   => 'একলাশপুর ইউনিয়ন, বেগমগঞ্জ, নোয়াখালী • চূড়ান্ত ভোটার তালিকা, প্রকাশ: ২৪/১১/২০২৫',
    'shop_name'  => 'মডার্ন কম্পিউটার',
    'shop_addr'  => 'একলাশপুর বাজার',
    'credit'     => 'মোঃ আলী হোসেন',
];
function settings(): array {
    static $s = null;
    if ($s !== null) return $s;
    $s = DEFAULT_SETTINGS;
    try { foreach (db()->query('SELECT k, v FROM settings') as $r) $s[$r['k']] = $r['v']; } catch (Throwable $e) {}
    return $s;
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function check_csrf(): void {
    $t = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('সেশন মেয়াদোত্তীর্ণ। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
    }
}

/* ---------- লগইন ---------- */
const REMEMBER_COOKIE = 'mc_remember';
const REMEMBER_DAYS = 30;

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0', 0, 45); }

function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    start_session();
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT id, username, full_name, role, active FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $row = $st->fetch();
        if ($row && $row['active']) $u = $row;
        else unset($_SESSION['uid']);
    }
    if (!$u && !empty($_COOKIE[REMEMBER_COOKIE])) $u = login_from_cookie();
    return $u;
}

function login_from_cookie(): ?array {
    $parts = explode(':', (string)$_COOKIE[REMEMBER_COOKIE]);
    if (count($parts) !== 2) { forget_cookie(); return null; }
    [$sel, $val] = $parts;
    $st = db()->prepare('SELECT t.id tid, t.validator_hash, u.id, u.username, u.full_name, u.role, u.active
        FROM remember_tokens t JOIN users u ON u.id = t.user_id WHERE t.selector = ? AND t.expires_at > NOW()');
    $st->execute([$sel]);
    $r = $st->fetch();
    if (!$r || !$r['active'] || !hash_equals($r['validator_hash'], hash('sha256', $val))) { forget_cookie(); return null; }
    db()->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$r['tid']]);
    do_login((int)$r['id'], true);
    return ['id' => $r['id'], 'username' => $r['username'], 'full_name' => $r['full_name'], 'role' => $r['role'], 'active' => $r['active']];
}

function do_login(int $uid, bool $remember): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$uid]);
    if ($remember) {
        $sel = bin2hex(random_bytes(9)); $val = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?,?,?, NOW() + INTERVAL ' . REMEMBER_DAYS . ' DAY)')
            ->execute([$uid, $sel, hash('sha256', $val)]);
        setcookie(REMEMBER_COOKIE, "$sel:$val", ['expires' => time() + REMEMBER_DAYS * 86400, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }
}

function forget_cookie(): void {
    if (!empty($_COOKIE[REMEMBER_COOKIE])) {
        $sel = explode(':', (string)$_COOKIE[REMEMBER_COOKIE])[0];
        try { db()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$sel]); } catch (Throwable $e) {}
    }
    setcookie(REMEMBER_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
}

function require_login(bool $json = false): array {
    if (!is_installed()) { header('Location: install.php'); exit; }
    $u = current_user();
    if ($u) return $u;
    if ($json) json_out(['error' => 'login'], 401);
    header('Location: login.php'); exit;
}

function require_admin(bool $json = false): array {
    $u = require_login($json);
    if ($u['role'] !== 'admin') {
        if ($json) json_out(['error' => 'forbidden'], 403);
        http_response_code(403); exit('শুধু অ্যাডমিন এই পেজ দেখতে পারবেন।');
    }
    return $u;
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function log_activity(?int $uid, string $action, string $detail = ''): void {
    try {
        db()->prepare('INSERT INTO activity_log (user_id, action, detail, ip) VALUES (?,?,?,?)')
            ->execute([$uid, $action, mb_substr($detail, 0, 250), client_ip()]);
    } catch (Throwable $e) {}
}

function send_security_headers(): void {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}
