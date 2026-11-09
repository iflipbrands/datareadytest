<?php
declare(strict_types=1);

const APP_NAME = 'DataReadyTest.online';

function private_dir(): string
{
    $configured = getenv('APP_DATA_DIR');
    $path = $configured !== false && $configured !== ''
        ? $configured
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private_data';
    $path = rtrim($path, DIRECTORY_SEPARATOR);
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Private storage is unavailable.');
    }
    @chmod($path, 0700);
    return $path;
}

function private_file(string $name): string
{
    if (!preg_match('/^[a-z0-9_-]+\.json$/', $name)) {
        throw new InvalidArgumentException('Invalid storage filename.');
    }
    return private_dir() . DIRECTORY_SEPARATOR . $name;
}

function read_json_file(string $name, array $fallback = []): array
{
    $path = private_file($name);
    if (!is_file($path)) {
        return $fallback;
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return $fallback;
    }
    try {
        if (!flock($handle, LOCK_SH)) {
            return $fallback;
        }
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : $fallback;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function update_json_file(string $name, callable $update, array $fallback = []): array
{
    $path = private_file($name);
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Private storage could not be opened.');
    }
    try {
        @chmod($path, 0600);
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Private storage is busy.');
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $current = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $current = is_array($current) ? $current : $fallback;
        $next = $update($current);
        if (!is_array($next)) {
            throw new RuntimeException('Invalid private storage update.');
        }
        $encoded = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $encoded . PHP_EOL) === false) {
            throw new RuntimeException('Private storage could not be written.');
        }
        fflush($handle);
        return $next;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function write_json_file(string $name, array $data): void
{
    update_json_file($name, static fn(array $current): array => $data);
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '3600');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}

function html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    start_secure_session();
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(mixed $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function admin_config(): array
{
    $config = read_json_file('admin.json');
    if (!isset($config['username'], $config['password_hash']) || !is_string($config['username']) || !is_string($config['password_hash'])) {
        throw new RuntimeException('Admin credentials have not been configured. Run setup-admin.php from the command line.');
    }
    return $config;
}

function admin_logged_in(): bool
{
    start_secure_session();
    return isset($_SESSION['admin_authenticated'], $_SESSION['admin_username'])
        && $_SESSION['admin_authenticated'] === true;
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: /admin.php');
        exit;
    }
}

function json_response(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
