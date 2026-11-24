<?php
/**
 * Админка сайта. Один файл: вход + интерфейс.
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/lib/admin.php';
admin_session();

if (!admin_ip_allowed()) {
    http_response_code(403);
    exit('Доступ в админку ограничен.');
}

if (!is_installed()) {
    redirect_to('setup.php');
}

$flash = flash_get();
$cssVer = @filemtime(ROOT . '/admin/assets/admin.css') ?: 1;
$jsVer = @filemtime(ROOT . '/admin/assets/admin.js') ?: 1;

if (!is_logged_in()) {
    ?>
    <!doctype html>
    <html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Вход — РостНержСталь</title>
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
            <span class="login__eyebrow">Админка</span>
            <h1>Вход</h1>
            <p class="login__lead">Введите пароль, который вы задали при установке сайта.</p>

            <?php if ($flash): ?>
                <div class="alert alert--<?= e($flash['type'] === 'success' ? 'success' : 'error') ?>">
                    <?= e((string)($flash['text'] ?? '')) ?>
                </div>
            <?php endif; ?>

            <form class="form" method="post" action="action.php?do=login">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <label class="field">
                    <span class="field__label">Пароль</span>
                    <input class="input" type="password" name="password" required autofocus
                           autocomplete="current-password" placeholder="••••••••">
                </label>
                <button class="btn-primary" type="submit">Войти</button>
            </form>

                <a class="login__back" href="../index.html">← Вернуться на сайт</a>
        </div>

        <p class="login__note">Сохраните эту страницу в закладки. Адрес входа: <b>/login</b>. Пароль забыли — смотрите раздел «Помощь» в инструкции.</p>
    </main>
    </body>
    </html>
    <?php
    exit;
}

$c = content_load();
$leads = leads_load();
$backups = backups_list();
$cfg = config_load();

$leadCount = count($leads);
$mailFrom = (string)($cfg['mail_from'] ?? '');
$leadTo = trim((string)($cfg['lead_email'] ?? (getenv('BM_LEAD_EMAIL') ?: '')));
if ($leadTo === '') {
    $leadTo = (string)(content_load()['site']['email'] ?? '');
}
$smtpHost = (string)($cfg['smtp_host'] ?? '');
$smtpPort = (int)($cfg['smtp_port'] ?? 465);
$smtpSecure = (string)($cfg['smtp_secure'] ?? 'ssl');
$smtpUser = (string)($cfg['smtp_user'] ?? '');
$smtpHasPassword = !empty($cfg['smtp_pass']);
$siteTitle = (string)($c['site']['title'] ?? 'РостНержСталь');
$contentJson = json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($contentJson === false) {
    $contentJson = '{}';
}

$leadRows = array_slice($leads, 0, 100);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf" content="<?= e(csrf_token()) ?>">
    <meta name="mail-from" content="<?= e($mailFrom) ?>">
    <meta name="lead-to" content="<?= e($leadTo) ?>">
    <title>Управление сайтом — РостНержСталь</title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../assets/css/fonts.css">
    <link rel="stylesheet" href="assets/admin.css?v=<?= $cssVer ?>">
</head>
<body class="admin-page">
<div class="admin">
    <aside class="sidebar">
        <div class="sidebar__brand">
            <span class="sidebar__mark">БМ</span>
            <span class="sidebar__name">РостНержСталь<small>управление сайтом</small></span>
        </div>

        <nav class="sidebar__nav" aria-label="Разделы админки">
            <button class="side-link is-active" type="button" data-view="content">
                <?= icon('doc', 'side-link__ico') ?><span>Контент</span>
            </button>
            <button class="side-link" type="button" data-view="visual">
                <?= icon('sparkle', 'side-link__ico') ?><span>Визуально</span>
            </button>
            <button class="side-link" type="button" data-view="leads">
                <?= icon('mail', 'side-link__ico') ?><span>Заявки</span>
                <?php if ($leadCount > 0): ?><span class="side-badge" data-leads-badge><?= $leadCount ?></span><?php endif; ?>
            </button>
            <button class="side-link" type="button" data-view="backups">
                <?= icon('clock', 'side-link__ico') ?><span>Резервные копии</span>
            </button>
            <button class="side-link" type="button" data-view="settings">
                <?= icon('shield', 'side-link__ico') ?><span>Настройки</span>
            </button>
            <button class="side-link" type="button" data-view="help">
                <?= icon('check', 'side-link__ico') ?><span>Помощь</span>
            </button>
        </nav>

        <div class="sidebar__foot">
            <a href="../index.html" target="_blank" rel="noopener">Открыть сайт ↗</a>
            <form class="sidebar__form" action="action.php?do=logout" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit">Выйти</button>
            </form>
        </div>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <div class="topbar__title">
                <h1 data-page-title>Контент</h1>
                <p data-page-sub>Тексты, фотографии и цены. После правок нажмите «Сохранить».</p>
            </div>
            <div class="topbar__actions">
                <span class="save-state" data-save-state>Всё сохранено</span>
                <button class="btn-save" type="button" data-save>
                    <span>Сохранить</span>
                </button>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="alert alert--<?= e($flash['type'] === 'success' ? 'success' : 'error') ?> alert--page">
                <?= e((string)($flash['text'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <main class="content">
            <section class="view is-active" data-view-panel="content">
                <div class="hint-box">
                    <b>Как это работает.</b> Выберите раздел, измените текст или фото и нажмите «Сохранить» вверху.
                    Сайт обновится сразу. Если что-то пошло не так — откройте «Резервные копии» и восстановите предыдущую версию.
                </div>
                <div class="editor-tools">
                    <button class="mini-btn" type="button" data-expand-all>Раскрыть все</button>
                    <button class="mini-btn" type="button" data-collapse-all>Свернуть все</button>
                </div>
                <div id="editor" class="editor">
                    <div class="editor-loading">Загружаем содержимое…</div>
                </div>
            </section>

            <section class="view" data-view-panel="visual">
                <div class="visual-tip">
                    <b>Визуальный режим.</b>
                    Нажмите на любой текст, чтобы изменить его прямо на странице. Нажмите на фотографию, чтобы заменить её.
                    Затем нажмите «Сохранить» вверху. Сложные списки и настройки удобнее менять в разделе «Контент».
                </div>
                <div class="visual-frame-wrap">
                    <iframe class="visual-frame" data-visual-frame title="Визуальный редактор сайта"
                            src="about:blank" loading="lazy"
                            sandbox="allow-scripts allow-same-origin"
                            referrerpolicy="no-referrer"></iframe>
                </div>
            </section>

            <section class="view" data-view-panel="leads">
                <div class="panel">
                    <div class="panel__head">
                        <div>
                            <h2>Заявки с сайта</h2>
                            <p>Показаны последние 100. Всего: <?= $leadCount ?>.</p>
                        </div>
                        <?php if ($leadCount > 0): ?>
                            <a class="btn-ghost" href="leads.php">Скачать CSV</a>
                        <?php endif; ?>
                    </div>

                    <?php if (!$leadRows): ?>
                        <div class="empty-state">
                            <b>Пока заявок нет.</b>
                            <p>Когда посетитель отправит форму, заявка появится здесь и придёт на почту.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Дата</th>
                                    <th>Имя</th>
                                    <th>Телефон</th>
                                    <th>Источник</th>
                                    <th>Страница</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($leadRows as $lead): ?>
                                    <?php
                                    $tel = preg_replace('/[^\d+]/', '', (string)($lead['phone'] ?? ''));
                                    $page = (string)($lead['page'] ?? '');
                                    ?>
                                    <tr>
                                        <td class="nowrap"><?= e((string)($lead['date'] ?? '')) ?></td>
                                        <td><?= e((string)($lead['name'] ?? '')) ?></td>
                                        <td><a href="tel:<?= e($tel) ?>"><?= e((string)($lead['phone'] ?? '')) ?></a></td>
                                        <td><span class="tag"><?= e((string)($lead['source'] ?? '')) ?></span></td>
                                        <td class="muted-cell">
                                            <?php if ($page !== ''): ?>
                                                <span title="<?= e($page) ?>"><?= e(mb_substr($page, 0, 46)) ?><?= mb_strlen($page) > 46 ? '…' : '' ?></span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="view" data-view-panel="backups">
                <div class="panel">
                    <div class="panel__head">
                        <div>
                            <h2>Резервные копии</h2>
                            <p>Сохраняются автоматически перед каждым изменением. Хранятся последние 30 версий.</p>
                        </div>
                    </div>

                    <div data-backups-list>
                        <?php if (!$backups): ?>
                            <div class="empty-state">
                                <b>Копий пока нет.</b>
                                <p>Они появятся после первого сохранения контента.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-wrap">
                                <table class="table">
                                    <thead>
                                    <tr>
                                        <th>Дата и время</th>
                                        <th>Размер</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr>
                                            <td><?= e(date('d.m.Y H:i:s', (int)$backup['time'])) ?></td>
                                            <td class="muted-cell"><?= e((string)round(((int)$backup['size']) / 1024)) ?> КБ</td>
                                            <td class="table__actions">
                                                <button class="mini-btn" type="button" data-restore="<?= e((string)$backup['name']) ?>">
                                                    Восстановить
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="view" data-view-panel="settings">
                <div class="panel">
                    <div class="panel__head">
                        <div>
                            <h2>Почта для заявок</h2>
                            <p>Здесь задаётся адрес, на который приходят заявки, и адрес отправителя.</p>
                        </div>
                    </div>

                    <form class="settings-form" data-settings-form>
                        <label class="field">
                            <span class="field__label">Куда приходят заявки</span>
                            <input class="input" type="email" name="lead_email" data-lead-email
                                   value="<?= e($leadTo) ?>" placeholder="info@steel-serves.by">
                            <span class="field__hint">
                                Сюда уходит каждая заявка с формы. Адрес не показывается посетителям сайта.
                            </span>
                        </label>
                        <label class="field">
                            <span class="field__label">E-mail отправителя</span>
                            <input class="input" type="email" name="mail_from" data-mail-from
                                   value="<?= e($mailFrom) ?>" placeholder="no-reply@steel-serves.by">
                            <span class="field__hint">
                                Ящик должен существовать на хостинге. Если сомневаетесь — оставьте как есть.
                            </span>
                        </label>
                        <div class="settings-subhead">Отправка через SMTP (необязательно)</div>
                        <p class="settings-help">
                            Без SMTP письма отправляются штатной функцией хостинга — это подходит для hoster.by.
                            Если сайт на Render или другом контейнере, заполните данные SMTP: так письма
                            будут уходить гарантированно.
                        </p>
                        <div class="settings-grid">
                            <label class="field">
                                <span class="field__label">SMTP-сервер</span>
                                <input class="input" type="text" name="smtp_host" data-smtp-host
                                       value="<?= e($smtpHost) ?>" placeholder="smtp.mail.ru">
                            </label>
                            <label class="field">
                                <span class="field__label">Порт</span>
                                <input class="input" type="number" name="smtp_port" data-smtp-port
                                       value="<?= e((string)$smtpPort) ?>" placeholder="465">
                            </label>
                            <label class="field">
                                <span class="field__label">Шифрование</span>
                                <select class="input" name="smtp_secure" data-smtp-secure>
                                    <option value="ssl"<?= $smtpSecure === 'ssl' ? ' selected' : '' ?>>SSL (порт 465)</option>
                                    <option value="tls"<?= $smtpSecure === 'tls' ? ' selected' : '' ?>>STARTTLS (порт 587)</option>
                                    <option value="none"<?= $smtpSecure === 'none' ? ' selected' : '' ?>>Без шифрования</option>
                                </select>
                            </label>
                            <label class="field">
                                <span class="field__label">Логин SMTP</span>
                                <input class="input" type="text" name="smtp_user" data-smtp-user
                                       value="<?= e($smtpUser) ?>" autocomplete="off">
                            </label>
                            <label class="field">
                                <span class="field__label">Пароль SMTP</span>
                                <input class="input" type="password" name="smtp_pass" data-smtp-pass
                                       placeholder="<?= $smtpHasPassword ? 'Сохранён — оставьте пустым' : 'Пароль приложения' ?>"
                                       autocomplete="new-password">
                                <span class="field__hint">Пустое поле — оставить сохранённый пароль.</span>
                            </label>
                        </div>
                        <div class="settings-actions">
                            <button class="btn-primary btn-primary--sm" type="submit">Сохранить настройки</button>
                            <button class="btn-ghost" type="button" data-test-mail>Проверить почту</button>
                        </div>
                        <span class="form-note" data-settings-status></span>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel__head">
                        <div>
                            <h2>Пароль</h2>
                            <p>Смените пароль, если он мог стать известен кому-то ещё.</p>
                        </div>
                    </div>

                    <form class="settings-form" data-password-form>
                        <label class="field">
                            <span class="field__label">Текущий пароль</span>
                            <input class="input" type="password" name="current_password" autocomplete="current-password">
                        </label>
                        <label class="field">
                            <span class="field__label">Новый пароль</span>
                            <input class="input" type="password" name="new_password" minlength="12" autocomplete="new-password">
                            <span class="field__hint">Минимум 12 символов. Лучше 14–20, с буквами, цифрами и знаком.</span>
                        </label>
                        <label class="field">
                            <span class="field__label">Новый пароль ещё раз</span>
                            <input class="input" type="password" name="new_password2" minlength="12" autocomplete="new-password">
                        </label>
                        <button class="btn-primary btn-primary--sm" type="submit">Сменить пароль</button>
                        <span class="form-note" data-password-status></span>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel__head">
                        <div>
                            <h2>Экспорт</h2>
                            <p>Скачайте копию всех текстов и настроек в формате JSON.</p>
                        </div>
                    </div>
                    <a class="btn-ghost" href="action.php?do=export">Скачать content.json</a>
                </div>
            </section>

            <section class="view" data-view-panel="help">
                <div class="panel">
                    <div class="panel__head">
                        <div>
                            <h2>Короткая инструкция</h2>
                            <p>Всё, что нужно знать для ежедневной работы.</p>
                        </div>
                    </div>

                    <div class="help">
                        <h3>1. Как изменить текст</h3>
                        <p>Откройте нужный раздел, найдите поле и отредактируйте его. Нажмите «Сохранить» вверху — сайт обновится сразу. Кнопка «Сохранить» активна только когда есть изменения.</p>

                        <h3>2. Как заменить фотографию</h3>
                        <p>Рядом с фотографией нажмите «Загрузить фото» и выберите файл JPG, PNG или WebP до 12 МБ. Сайт сам сожмёт его и сделает все нужные размеры. Не нужно ничего переименовывать вручную.</p>

                        <h3>3. Как добавить услугу, отзыв или вопрос</h3>
                        <p>В разделе есть кнопка «Добавить». Новый пункт появится в конце списка. Кнопки «↑» и «↓» меняют порядок, «Удалить» убирает пункт. Перед удалением сайт сохраняет резервную копию.</p>

                        <h3>4. Если что-то сломалось</h3>
                        <p>Откройте «Резервные копии», найдите версию до неудачного изменения и нажмите «Восстановить». Сайт вернётся к ней. Хранятся 30 последних версий.</p>

                        <h3>5. Заявки и почта</h3>
                        <p>Получатель задаётся в «Настройки → Куда приходят заявки». Заявки всегда дублируются в разделе «Заявки» — даже если письмо не ушло.</p>
                        <p>На hoster.by достаточно указать ящик отправителя на домене (<code>no-reply@steel-serves.by</code>) и настроить SPF/DKIM. На Render и других контейнерах заполните блок SMTP: сервер, порт, шифрование, логин и пароль приложения. Кнопка «Проверить почту» покажет, работает ли настройка.</p>

                        <h3>6. Пароль и адрес входа</h3>
                        <p>Панель открывается по адресу <code>/login</code> (он же <code>/admin/</code>). Сохраните страницу входа в закладки браузера, чтобы не набирать адрес вручную.</p>
                        <p>Пароль хранится в файле <code>data/config.php</code> только в виде необратимого хеша. Если вы его забыли: через FTP переименуйте <code>config.php</code> в <code>config-old.php</code>, удалите файл <code>data/setup.lock</code>, откройте <code>/admin/setup.php</code>, задайте новый пароль и удалите старый файл.</p>

                        <h3>7. Что нельзя удалять</h3>
                        <p>Папки <code>data</code>, <code>uploads</code>, <code>lib</code>, <code>templates</code> и файл <code>index.html</code> в корне. Это контент, фотографии и сам сайт.</p>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<div class="toast" data-toast role="status" aria-live="polite"></div>
<textarea id="content-data" hidden><?= e($contentJson) ?></textarea>
<script src="assets/admin.js?v=<?= $jsVer ?>" defer></script>
</body>
</html>
