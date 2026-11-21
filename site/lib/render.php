<?php
/**
 * Рендеринг сайта и вспомогательные функции.
 * Работает и при сборке из админки, и при первичной генерации index.html.
 */

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Телефон не должен разрываться по строкам в узких колонках. */
function phone_typography(?string $s): string
{
    return str_replace([' ', '-'], ['&nbsp;', '&#8209;'], e($s));
}

/**
 * Атрибуты для визуального редактора. В обычной сборке ничего не выводят:
 * index.html остаётся чистым, без служебной разметки.
 */
function bm(string $path, string $type = 'text'): string
{
    if (!defined('BM_EDIT') || $path === '') {
        return '';
    }
    return ' data-bm="' . e($path) . '" data-bm-type="' . e($type) . '"';
}

function bm_img(string $path, string $altPath = ''): string
{
    if (!defined('BM_EDIT') || $path === '') {
        return '';
    }
    $out = ' data-bm-img="' . e($path) . '"';
    if ($altPath !== '') {
        $out .= ' data-bm-alt="' . e($altPath) . '"';
    }
    return $out;
}

function content_path(): string
{
    return ROOT . '/data/content.json';
}

function content_load(): array
{
    $raw = @file_get_contents(content_path());
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function content_defaults(): array
{
    static $defaults = null;
    if ($defaults === null) {
        $defaults = content_load();
    }
    return $defaults;
}

/** Глубокое слияние сохранённого контента с дефолтной структурой. */
function content_merge(array $saved): array
{
    $defaults = content_defaults();
    $merge = function ($def, $cur) use (&$merge) {
        if (!is_array($def) || !is_array($cur)) {
            return $cur === null ? $def : $cur;
        }
        $isList = array_keys($def) === range(0, count($def) - 1);
        if ($isList) {
            return $cur;
        }
        $out = $def;
        foreach ($cur as $k => $v) {
            $out[$k] = array_key_exists($k, $def) ? $merge($def[$k], $v) : $v;
        }
        return $out;
    };
    return $merge($defaults, $saved);
}

function content_save(array $content, bool $render = true): array
{
    $path = content_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return [false, 'Не удалось создать папку data'];
    }
    if (!is_writable($dir)) {
        return [false, 'Папка data недоступна для записи. Установите права 755/775.'];
    }

    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        return [false, 'Ошибка кодирования JSON'];
    }

    // Бэкап предыдущей версии
    if (is_file($path)) {
        $bdir = ROOT . '/data/backups';
        if (!is_dir($bdir)) {
            @mkdir($bdir, 0775, true);
        }
        $stamp = date('Ymd-His');
        @copy($path, $bdir . '/content-' . $stamp . '.json');
        $files = glob($bdir . '/content-*.json') ?: [];
        if (count($files) > 30) {
            usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
            foreach (array_slice($files, 0, count($files) - 30) as $old) {
                @unlink($old);
            }
        }
    }

    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return [false, 'Не удалось записать content.json'];
    }
    if (!@rename($tmp, $path)) {
        $saved = @file_put_contents($path, $json, LOCK_EX) !== false;
        @unlink($tmp);
        if (!$saved) {
            return [false, 'Не удалось заменить content.json'];
        }
    }

    if ($render) {
        $html = render_site($content);
        $index = ROOT . '/index.html';
        $tmp2 = ROOT . '/index.html.tmp';
        if (@file_put_contents($tmp2, $html, LOCK_EX) === false) {
            return [false, 'Контент сохранён, но не удалось записать index.html'];
        }
        if (!@rename($tmp2, $index)) {
            $built = @file_put_contents($index, $html, LOCK_EX) !== false;
            @unlink($tmp2);
            if (!$built) {
                return [false, 'Контент сохранён, но не удалось заменить index.html'];
            }
        }
    }
    return [true, ''];
}

function render_site(array $c): string
{
    ob_start();
    include ROOT . '/templates/page.php';
    return ob_get_clean();
}

/* ---------- Изображения ---------- */

function img_path(string $src): string
{
    return ROOT . '/' . ltrim($src, '/');
}

function img_meta(string $src): array
{
    static $cache = [];
    if (isset($cache[$src])) {
        return $cache[$src];
    }
    $w = 0;
    $h = 0;
    $p = img_path($src);
    if (is_file($p)) {
        $info = @getimagesize($p);
        if ($info) {
            $w = (int)$info[0];
            $h = (int)$info[1];
        }
    }
    return $cache[$src] = [$w, $h];
}

function srcset_for(string $src, string $ext, int $baseW): string
{
    $base = preg_replace('/\.[a-z0-9]+$/i', '', $src);
    $items = [];
    foreach ([320, 400, 480, 640, 800, 960, 1200, 1280, 1600, 1920] as $w) {
        if ($w >= $baseW) {
            continue;
        }
        $f = $base . '-' . $w . '.' . $ext;
        if (is_file(img_path($f))) {
            $items[] = $f . ' ' . $w . 'w';
        }
    }
    $baseFile = $base . '.' . $ext;
    if (is_file(img_path($baseFile)) && $baseW > 0) {
        $items[] = $baseFile . ' ' . $baseW . 'w';
    }
    return implode(', ', $items);
}

/**
 * <picture> с AVIF/WebP и srcset.
 * $opts: class, loading, fetchpriority, sizes, width, height, id
 */
function picture(string $src, string $alt, string $sizes = '100vw', array $opts = []): string
{
    if ($src === '') {
        return '';
    }
    [$w, $h] = img_meta($src);
    $avif = srcset_for($src, 'avif', $w);
    $webp = srcset_for($src, 'webp', $w);
    $loading = $opts['loading'] ?? 'lazy';
    $priority = $opts['fetchpriority'] ?? '';
    $class = $opts['class'] ?? '';
    $attrs = '';
    if ($class !== '') {
        $attrs .= ' class="' . e($class) . '"';
    }
    if ($w > 0 && $h > 0) {
        $attrs .= ' width="' . $w . '" height="' . $h . '"';
    }
    $attrs .= ' loading="' . e($loading) . '" decoding="async"';
    if ($priority !== '') {
        $attrs .= ' fetchpriority="' . e($priority) . '"';
    }
    if (isset($opts['edit'])) {
        $attrs .= bm_img((string)$opts['edit'], (string)($opts['edit_alt'] ?? ''));
    }

    $out = '<picture>';
    if ($avif !== '') {
        $out .= '<source type="image/avif" srcset="' . e($avif) . '" sizes="' . e($sizes) . '">';
    }
    if ($webp !== '') {
        $out .= '<source type="image/webp" srcset="' . e($webp) . '" sizes="' . e($sizes) . '">';
    }
    $out .= '<img src="' . e($src) . '" alt="' . e($alt) . '"' . $attrs . '>';
    $out .= '</picture>';
    return $out;
}

function preload_picture(string $src, string $sizes): string
{
    [$w] = img_meta($src);
    if ($w <= 0) {
        return '';
    }
    $out = '';
    $avif = srcset_for($src, 'avif', $w);
    if ($avif !== '') {
        $avifHref = preg_replace('/\.(webp|jpe?g|png)$/i', '.avif', $src) ?: $src;
        $out .= '<link rel="preload" as="image" type="image/avif" href="' . e($avifHref) . '" imagesrcset="' . e($avif) . '" imagesizes="' . e($sizes) . '">' . "\n";
    }
    $webp = srcset_for($src, 'webp', $w);
    if ($webp !== '') {
        $out .= '<link rel="preload" as="image" type="image/webp" href="' . e($src) . '" imagesrcset="' . e($webp) . '" imagesizes="' . e($sizes) . '">' . "\n";
    }
    return $out;
}

/* ---------- Иконки ---------- */

function icon(string $name, string $class = 'ico'): string
{
    $paths = [
        'factory' => '<path d="M3 21V10l5.5 3.5V10L14 13.5V4h7v17H3Z"/><path d="M7 21v-3M12 21v-3M17 21v-3"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'steel' => '<path d="M4 8.5 12 4l8 4.5-8 4.5-8-4.5Z"/><path d="m4 12.5 8 4.5 8-4.5"/><path d="m4 16.5 8 4.5 8-4.5"/>',
        'shield' => '<path d="M12 3.5 19 6v5.5c0 4.2-2.8 7.4-7 9-4.2-1.6-7-4.8-7-9V6l7-2.5Z"/><path d="m9 12 2 2 4-4"/>',
        'ruler' => '<path d="m4 16 12-12 4 4L8 20l-4-4Z"/><path d="m9 11 1.5 1.5M12 8l1.5 1.5M15 5l1.5 1.5"/>',
        'sparkle' => '<path d="M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 18.2l-1.8-5.6L4.5 10.8 10.2 9 12 3.5Z"/><path d="M18.5 16.5 19.2 18.8 21.5 19.5l-2.3.7-.7 2.3-.7-2.3-2.3-.7 2.3-.7.7-2.3Z"/>',
        'arrow' => '<path d="M5 12h13"/><path d="m13 6 5 6-5 6"/>',
        'phone' => '<path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7 2 2 0 0 1 6.5 3.5Z"/>',
        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="1.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'pin' => '<path d="M12 21s6.5-6.1 6.5-11a6.5 6.5 0 1 0-13 0C5.5 14.9 12 21 12 21Z"/><circle cx="12" cy="10" r="2.2"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'truck' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/>',
        'doc' => '<path d="M6 3.5h8l4 4V20.5H6z"/><path d="M14 3.5v4h4"/><path d="M9 12h6M9 15.5h6"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 9h18"/><path d="M16 13.5h2"/>',
    ];
    $p = $paths[$name] ?? $paths['check'];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/* ---------- Формы ---------- */

function lead_form(string $id, string $source, string $title, string $text, string $submit = '', string $editPrefix = ''): string
{
    $c = content_defaults();
    $f = $c['forms'] ?? [];
    $submit = $submit !== '' ? $submit : ($f['submit'] ?? 'Отправить');
    $consent = $f['consent'] ?? 'Согласен на обработку персональных данных';
    $link = $f['consent_link'] ?? 'Политикой конфиденциальности';
    $nameLabel = $f['name_label'] ?? 'Ваше имя';
    $phoneLabel = $f['phone_label'] ?? 'Телефон';
    $namePh = $f['name_placeholder'] ?? 'Имя';
    $phonePh = $f['phone_placeholder'] ?? '+375 (__) ___-__-__';

    ob_start(); ?>
    <form class="lead-form" data-lead-form data-source="<?= e($source) ?>"
          data-sending="<?= e($f['sending'] ?? 'Отправляем…') ?>"
          data-error="<?= e($f['error'] ?? 'Не удалось отправить заявку. Позвоните нам — так быстрее.') ?>" novalidate>
        <div class="lead-form__head">
            <h3 class="lead-form__title"<?= $editPrefix !== '' ? bm($editPrefix . '_title') : '' ?>><?= e($title) ?></h3>
            <?php if ($text !== ''): ?><p class="lead-form__text"<?= $editPrefix !== '' ? bm($editPrefix . '_text') : '' ?>><?= e($text) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label class="field__label" for="name-<?= e($id) ?>"><?= e($nameLabel) ?></label>
            <input class="field__input" id="name-<?= e($id) ?>" name="name" type="text" autocomplete="name"
                   placeholder="<?= e($namePh) ?>" required minlength="2" maxlength="80">
            <span class="field__error" data-error-for="name" aria-live="polite"></span>
        </div>
        <div class="field">
            <label class="field__label" for="phone-<?= e($id) ?>"><?= e($phoneLabel) ?></label>
            <input class="field__input" id="phone-<?= e($id) ?>" name="phone" type="tel" inputmode="tel"
                   autocomplete="tel" placeholder="<?= e($phonePh) ?>" required>
            <span class="field__error" data-error-for="phone" aria-live="polite"></span>
        </div>
        <div class="hp" aria-hidden="true">
            <label for="website-<?= e($id) ?>">Website</label>
            <input id="website-<?= e($id) ?>" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <div class="check">
            <input class="check__input" id="consent-<?= e($id) ?>" type="checkbox" name="consent" required>
            <span class="check__box" aria-hidden="true"><?= icon('check', 'ico check__ico') ?></span>
            <span class="check__text"><label for="consent-<?= e($id) ?>"><?= e($consent) ?></label>. Ознакомлен с <button type="button" class="link-inline" data-open-privacy><?= e($link) ?></button>.</span>
        </div>
        <button class="btn btn--accent btn--full" type="submit">
            <span data-submit-text><?= e($submit) ?></span>
            <?= icon('arrow', 'btn__ico') ?>
        </button>
        <p class="form-status" data-form-status role="status" aria-live="polite"></p>
        <input type="hidden" name="ts" value="<?= time() ?>">
        <input type="hidden" name="page" value="">
        <input type="hidden" name="utm" value="">
    </form>
    <?php
    return ob_get_clean();
}
