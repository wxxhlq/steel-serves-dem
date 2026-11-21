<?php
/**
 * Пересобирает index.html из data/content.json при каждом старте контейнера.
 * Нужно, чтобы после рестарта с постоянным диском сайт показывал сохранённые правки.
 */
define('ROOT', '/var/www/html');
require ROOT . '/lib/render.php';

$content = content_load();
if (!$content) {
    fwrite(STDERR, "Не удалось прочитать data/content.json\n");
    exit(1);
}
$html = render_site($content);
if (file_put_contents(ROOT . '/index.html', $html) === false) {
    fwrite(STDERR, "Не удалось записать index.html\n");
    exit(1);
}
echo "index.html rebuilt: " . strlen($html) . " bytes\n";
