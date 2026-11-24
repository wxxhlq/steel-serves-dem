<?php
/**
 * Первый запуск: установка пароля администратора.
 * Повторно открыть нельзя, пока существует data/config.php.
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/lib/admin.php';
admin_session();

if (is_installed()) {
    redirect_to('index.php');
}
if (is_file(setup_lock_path())) {
    http_response_code(403);
    exit('Установка уже выполнена. Восстановление из резервной копии доступно только владельцу доступа к файлам сайта.');
}

$errors = [];
$password = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'Сессия истекла. Обновите страницу и попробуйте снова.';
    }

    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if (mb_strlen($password, 'UTF-8') < 12) {
        $errors[] = 'Пароль должен быть не короче 12 символов. Лучше 14–20.';
    }
    if ($password !== $password2) {
        $errors[] = 'Пароли не совпадают.';
    }
    if ($password !== '' && preg_match('/^(12345678|password|qwerty123|admin123|123456789|steel-serves|базa-металла)$/iu', $password)) {
        $errors[] = 'Такой пароль слишком простой. Придумайте другой.';
    }

    if (!$errors) {
        $content = content_load();
        $host = parse_url((string)($content['site']['domain'] ?? ''), PHP_URL_HOST) ?: 'steel-serves.by';

        $cfg = [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'secret' => bin2hex(random_bytes(32)),
            'mail_from' => 'no-reply@' . $host,
            'created' => time(),
            'password_version' => time(),
        ];

        if (config_save($cfg)) {
            @file_put_contents(setup_lock_path(), 'installed=' . time() . "\n", LOCK_EX);
            @chmod(setup_lock_path(), 0600);
            session_regenerate_id(true);
            $_SESSION['bm_admin'] = (string)$cfg['password_version'];
            $_SESSION['bm_csrf'] = bin2hex(random_bytes(24));
            flash_set('Пароль установлен. Теперь можно редактировать сайт.', 'success');
            redirect_to('index.php');
        }

        $errors[] = 'Не удалось записать data/config.php. Проверьте права на папку data (755 или 775).';
    }
}

$cssVer = @filemtime(ROOT . '/admin/assets/admin.css') ?: 1;
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Установка админки — РостНержСталь</title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="assets/admin.css?v=<?= $cssVer ?>">
</head>
<body class="login-page">
<main class="login">
    <div class="login__brand">
        <span class="login__mark">БМ</span>
        <span class="login__brand-text">
            <b>РостНержСталь</b>
            <span>панель управления сайтом</span>
        </span>
    </div>

    <div class="login__card">
        <span class="login__eyebrow">Первый запуск</span>
        <h1>Задайте пароль</h1>
        <p class="login__lead">Этот пароль будете использовать вы и только вы. Запишите его в надёжном месте — восстановление возможно только через файлы сайта.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form class="form" method="post" action="setup.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="field">
                <span class="field__label">Пароль</span>
                <input class="input" type="password" name="password" minlength="12" required autofocus
                       autocomplete="new-password">
                <span class="field__hint">Минимум 12 символов. Лучше 14–20, с буквами, цифрами и символами.</span>
            </label>
            <label class="field">
                <span class="field__label">Повторите пароль</span>
                <input class="input" type="password" name="password2" minlength="12" required autocomplete="new-password">
            </label>
            <button class="btn-primary" type="submit">Установить пароль и войти</button>
        </form>

        <a class="login__back" href="../index.html">← Вернуться на сайт</a>
    </div>

    <p class="login__note">После установки страница setup.php перестанет открываться. Это нормально.</p>
</main>
</body>
</html>
