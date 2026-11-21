<?php
/**
 * Живой предпросмотр сайта для визуального редактора.
 * Доступен только после входа в админку. В обычной сборке не используется:
 * index.html остаётся статическим и чистым.
 */

define('ROOT', __DIR__);
require ROOT . '/lib/admin.php';
admin_session();

if (!is_logged_in()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Нет доступа</title>'
        . '<body style="font:16px/1.5 system-ui;padding:40px">Нет доступа. Войдите в админку и откройте редактор снова.</body></html>';
    exit;
}

define('BM_EDIT', true);

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

$content = content_load();
echo render_site($content);
