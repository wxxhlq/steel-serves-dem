<?php
/**
 * Админка: сессия, конфиг, CSRF, заявки, резервные копии.
 * Этот файл не отдаёт HTML — только функции.
 */

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}
require_once ROOT . '/lib/render.php';

/* ---------- Базовые ответы ---------- */

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect_to(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

function current_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Необязательный белый список IP для админки.
 * В data/config.php добавьте, например:
 *   'admin_ips' => ['203.0.113.10'],
 * Пустой список = доступ с любого IP после ввода валидного пароля.
 */
function admin_ip_allowed(): bool
{
    $ips = config_load()['admin_ips'] ?? null;
    if (!is_array($ips) || count($ips) === 0) {
        return true;
    }
    $allowed = [];
    foreach ($ips as $ip) {
        $ip = trim((string)$ip);
        if ($ip !== '') {
            $allowed[] = $ip;
        }
    }
    return $allowed !== [] && in_array(current_ip(), $allowed, true);
}

/* ---------- Сессия ---------- */

function admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_name('bmadmin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['bm_csrf'])) {
        $_SESSION['bm_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['bm_csrf'];
}

function csrf_check(?string $token): bool
{
    return is_string($token) && $token !== '' && !empty($_SESSION['bm_csrf'])
        && hash_equals($_SESSION['bm_csrf'], $token);
}

function flash_set(string $text, string $type = 'error'): void
{
    $_SESSION['bm_flash'] = ['text' => $text, 'type' => $type];
}

function flash_get(): ?array
{
    if (empty($_SESSION['bm_flash'])) {
        return null;
    }
    $flash = $_SESSION['bm_flash'];
    unset($_SESSION['bm_flash']);
    return is_array($flash) ? $flash : null;
}

/* ---------- Конфиг ---------- */

function config_path(): string
{
    return ROOT . '/data/config.php';
}

function config_load(bool $fresh = false): array
{
    static $cache = null;
    if ($fresh) {
        $cache = null;
    }
    if ($cache !== null) {
        return $cache;
    }
    if (!is_file(config_path())) {
        return $cache = [];
    }
    $cfg = include config_path();
    return $cache = is_array($cfg) ? $cfg : [];
}

function config_save(array $cfg): bool
{
    $cfg['updated'] = time();
    $ok = store_write(config_path(), $cfg);
    if ($ok) {
        config_load(true);
    }
    return $ok;
}

function setup_lock_path(): string
{
    return ROOT . '/data/setup.lock';
}

function rate_secret(): string
{
    $secret = (string)(config_load()['secret'] ?? '');
    return $secret !== '' ? $secret : 'rate-limit-v1';
}

function password_version(): int
{
    $cfg = config_load();
    $version = $cfg['password_version'] ?? $cfg['created'] ?? 1;
    return (int)$version;
}

function is_installed(): bool
{
    $cfg = config_load();
    return !empty($cfg['password_hash']);
}

function is_logged_in(): bool
{
    if (empty($_SESSION['bm_admin'])) {
        return false;
    }
    return hash_equals((string)password_version(), (string)$_SESSION['bm_admin']);
}

function require_auth(): void
{
    if (!admin_ip_allowed()) {
        http_response_code(403);
        exit('Доступ в админку ограничен.');
    }
    if (!is_logged_in()) {
        redirect_to('index.php');
    }
}

function require_auth_json(): void
{
    if (!admin_ip_allowed()) {
        json_out(['ok' => false, 'message' => 'Доступ в админку ограничен.'], 403);
    }
    if (!is_logged_in()) {
        json_out(['ok' => false, 'message' => 'Сессия истекла. Войдите снова.'], 401);
    }
}

/** Локальное изображение из assets/ или uploads/, без обхода каталогов. */
function local_img_src(string $src): string
{
    $src = trim($src);
    if (!preg_match('#^(assets|uploads)/[A-Za-z0-9._~/%-]+$#', $src) || str_contains($src, '..')) {
        return '';
    }
    return $src;
}

/* ---------- Хранилище ---------- */

function store_read(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $data = include $path;
    return is_array($data) ? $data : [];
}

function store_write(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $code = "<?php\nreturn " . var_export($data, true) . ";\n";
    $tmp = $path . '.tmp-' . getmypid();
    if (@file_put_contents($tmp, $code, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0600);
    if (@rename($tmp, $path)) {
        return true;
    }
    // Windows / нестандартные ФС: прямая запись как запасной вариант.
    $ok = @file_put_contents($path, $code, LOCK_EX) !== false;
    @unlink($tmp);
    return $ok;
}

/* ---------- Заявки ---------- */

function leads_path(): string
{
    return ROOT . '/data/leads.php';
}

function leads_load(): array
{
    return store_read(leads_path());
}

function leads_append(array $lead): bool
{
    $lockPath = ROOT . '/data/.leads.lock';
    $lock = @fopen($lockPath, 'c');
    if ($lock) {
        @flock($lock, LOCK_EX);
    }
    $leads = leads_load();
    array_unshift($leads, $lead);
    if (count($leads) > 500) {
        $leads = array_slice($leads, 0, 500);
    }
    $ok = store_write(leads_path(), $leads);
    if ($lock) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
    return $ok;
}

/* ---------- Ограничения ---------- */

function rate_check(string $bucket, string $ip, int $limit, int $window): array
{
    $path = ROOT . '/data/rate.php';
    $key = hash('sha256', $bucket . '|' . $ip . '|' . rate_secret());
    $now = time();

    $lock = @fopen(ROOT . '/data/.rate.lock', 'c');
    if ($lock) {
        @flock($lock, LOCK_EX);
    }

    $data = store_read($path);
    $hits = array_values(array_filter($data[$key] ?? [], fn($t) => is_int($t) && $t > $now - $window));

    if (count($hits) >= $limit) {
        $retry = max(1, min($hits) + $window - $now);
        if ($lock) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
        return ['ok' => false, 'retry' => $retry];
    }

    $hits[] = $now;
    $data[$key] = $hits;

    // Чистим старые ключи, чтобы файл не рос бесконечно.
    if (count($data) > 400) {
        foreach ($data as $k => $rows) {
            $rows = array_filter((array)$rows, fn($t) => is_int($t) && $t > $now - 3600);
            if (!$rows) {
                unset($data[$k]);
            } else {
                $data[$k] = array_values($rows);
            }
        }
    }

    store_write($path, $data);

    if ($lock) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
    return ['ok' => true, 'retry' => 0];
}

function login_state(string $ip): array
{
    $lock = @fopen(ROOT . '/data/.login.lock', 'c');
    if ($lock) {
        @flock($lock, LOCK_EX);
    }
    $data = store_read(ROOT . '/data/login.php');
    $key = hash('sha256', $ip . '|' . rate_secret());
    $now = time();
    $hits = array_values(array_filter($data[$key] ?? [], fn($t) => is_int($t) && $t > $now - 900));
    if ($lock) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
    if (count($hits) >= 5) {
        return ['blocked' => true, 'retry' => max(1, min($hits) + 900 - $now)];
    }
    return ['blocked' => false, 'retry' => 0];
}

function login_fail(string $ip): void
{
    $path = ROOT . '/data/login.php';
    $lock = @fopen(ROOT . '/data/.login.lock', 'c');
    if ($lock) {
        @flock($lock, LOCK_EX);
    }
    $data = store_read($path);
    $key = hash('sha256', $ip . '|' . rate_secret());
    $now = time();
    $hits = array_values(array_filter($data[$key] ?? [], fn($t) => is_int($t) && $t > $now - 900));
    $hits[] = $now;
    $data[$key] = $hits;
    store_write($path, $data);
    if ($lock) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function login_clear(string $ip): void
{
    $path = ROOT . '/data/login.php';
    $lock = @fopen(ROOT . '/data/.login.lock', 'c');
    if ($lock) {
        @flock($lock, LOCK_EX);
    }
    $data = store_read($path);
    $key = hash('sha256', $ip . '|' . rate_secret());
    unset($data[$key]);
    store_write($path, $data);
    if ($lock) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/* ---------- Журнал ошибок почты ---------- */

function create_log_entry(string $line): void
{
    $path = ROOT . '/data/mail.log';
    if (!is_dir(dirname($path))) {
        return;
    }
    if (is_file($path) && filesize($path) > 512 * 1024) {
        @unlink($path . '.1');
        @rename($path, $path . '.1');
    }
    @file_put_contents(
        $path,
        '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $line) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/* ---------- Бэкапы ---------- */

function backups_list(): array
{
    $files = glob(ROOT . '/data/backups/content-*.json') ?: [];
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    $out = [];
    foreach ($files as $file) {
        $out[] = [
            'name' => basename($file),
            'time' => filemtime($file),
            'size' => filesize($file),
        ];
    }
    return $out;
}

/* ---------- Политика (HTML) ---------- */

function sanitize_policy_html(string $html): string
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
    $html = strip_tags($html, '<h2><h3><h4><p><ul><ol><li><strong><em><br><a>');
    // Полностью отбрасываем атрибуты у простых тегов, чтобы убрать style/on*.
    $html = preg_replace('#<(h2|h3|h4|p|ul|ol|li|strong|em|br)\b[^>]*>#i', '<$1>', $html) ?? '';

    $html = preg_replace_callback('#<a\b[^>]*>#i', function ($m) {
        $href = '#';
        if (preg_match('/href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $m[0], $h)) {
            $candidate = trim($h[1] !== '' ? $h[1] : ($h[2] !== '' ? $h[2] : $h[3]));
            $candidate = preg_replace('/[\x00-\x1F\x7F]+/', '', $candidate) ?? '';
            if (preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $candidate) && !str_contains($candidate, '..')) {
                $href = $candidate;
            }
        }
        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" rel="noopener noreferrer">';
    }, $html) ?? '';

    return str_replace("\0", '', $html);
}
