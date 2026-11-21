<?php
/**
 * Генерирует data/config.php с заданным паролем.
 * Используется при сборке поставки: php make_config.php <путь-к-config.php>
 * Пароль передаётся через переменную окружения BM_ADMIN_PASSWORD.
 */

$target = $argv[1] ?? '';
$password = getenv('BM_ADMIN_PASSWORD') ?: '';

if ($target === '') {
    fwrite(STDERR, "usage: php make_config.php <path-to-config.php>\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "BM_ADMIN_PASSWORD must be at least 12 characters\n");
    exit(1);
}

$cfg = [
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'secret' => bin2hex(random_bytes(32)),
    'mail_from' => 'no-reply@steel-serves.by',
    'created' => time(),
    'password_version' => time(),
];

$dir = dirname($target);
if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    fwrite(STDERR, "cannot create directory: $dir\n");
    exit(1);
}

$code = "<?php\nreturn " . var_export($cfg, true) . ";\n";
if (file_put_contents($target, $code, LOCK_EX) === false) {
    fwrite(STDERR, "cannot write: $target\n");
    exit(1);
}
@chmod($target, 0600);

echo "config written: $target\n";
