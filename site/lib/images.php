<?php
/**
 * Загрузка и оптимизация изображений через GD.
 * На выходе — WebP + AVIF в нескольких ширинах.
 */

function upload_error_text(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Файл слишком большой. Загрузите фото до 12 МБ.';
        case UPLOAD_ERR_PARTIAL:
            return 'Файл загрузился не полностью. Попробуйте ещё раз.';
        case UPLOAD_ERR_NO_FILE:
            return 'Файл не выбран.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'Сервер не может сохранить файл. Проверьте права на папку uploads.';
        default:
            return 'Не удалось загрузить файл.';
    }
}

function translit_slug(string $text): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, $map);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function gd_resize($src, int $width, int $height)
{
    $dst = imagecreatetruecolor($width, $height);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));
    return $dst;
}

/**
 * @return array{0:bool,1:string,2?:array}
 */
function process_upload(array $file, string $relDir = 'uploads'): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, 'Некорректная загрузка файла.'];
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return [false, upload_error_text((int)$file['error'])];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return [false, 'Файл не найден на сервере. Попробуйте ещё раз.'];
    }

    $maxBytes = 12 * 1024 * 1024;
    $size = (int)($file['size'] ?? filesize($tmp));
    if ($size <= 0 || $size > $maxBytes) {
        return [false, 'Файл слишком большой. Максимум 12 МБ.'];
    }

    $info = @getimagesize($tmp);
    $mime = (string)($info['mime'] ?? '');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($allowed[$mime])) {
        return [false, 'Поддерживаются только JPG, PNG и WebP.'];
    }

    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w < 300 || $h < 300) {
        return [false, 'Фото слишком маленькое. Минимум 300 × 300 пикселей.'];
    }
    if ($w > 12000 || $h > 12000 || ($w * $h) > 60000000) {
        return [false, 'Фото слишком большое по разрешению. Максимум 12000 пикселей по стороне.'];
    }

    @ini_set('memory_limit', '256M');
    @set_time_limit(90);

    $raw = @file_get_contents($tmp);
    if ($raw === false) {
        return [false, 'Не удалось прочитать файл.'];
    }

    $src = @imagecreatefromstring($raw);
    if (!$src) {
        return [false, 'Не удалось обработать изображение. Попробуйте другой файл.'];
    }

    // Поворот по EXIF (телефоны часто сохраняют фото боком).
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $orientation = (int)($exif['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle !== 0) {
            $rotated = @imagerotate($src, $angle, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
                $w = imagesx($src);
                $h = imagesy($src);
            }
        }
    }

    if (!imageistruecolor($src)) {
        imagepalettetotruecolor($src);
    }

    $baseW = min($w, 1920);
    $baseH = max(1, (int)round($h * $baseW / $w));

    $dir = ROOT . '/' . trim($relDir, '/');
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        imagedestroy($src);
        return [false, 'Не удалось создать папку uploads.'];
    }
    if (!is_writable($dir)) {
        imagedestroy($src);
        return [false, 'Папка uploads недоступна для записи. Установите права 755/775.'];
    }

    $slug = translit_slug(pathinfo((string)($file['name'] ?? 'photo'), PATHINFO_FILENAME));
    $name = 'u-' . date('Ymd-His') . '-' . ($slug !== '' ? mb_substr($slug, 0, 40) . '-' : '') . bin2hex(random_bytes(3));
    $basePath = $dir . '/' . $name;

    $base = gd_resize($src, $baseW, $baseH);
    if (!@imagewebp($base, $basePath . '.webp', 82)) {
        imagedestroy($base);
        imagedestroy($src);
        return [false, 'Не удалось сохранить WebP. Проверьте поддержку GD.'];
    }
    if (function_exists('imageavif')) {
        @imageavif($base, $basePath . '.avif', 55);
    }

    $variantWidths = [400, 640, 960, 1600];
    foreach ($variantWidths as $vw) {
        if ($vw >= $baseW) {
            continue;
        }
        $vh = max(1, (int)round($h * $vw / $w));
        $variant = gd_resize($src, $vw, $vh);
        @imagewebp($variant, $basePath . '-' . $vw . '.webp', 82);
        if (function_exists('imageavif')) {
            @imageavif($variant, $basePath . '-' . $vw . '.avif', 55);
        }
        imagedestroy($variant);
    }

    imagedestroy($base);
    imagedestroy($src);

    $rel = trim($relDir, '/') . '/' . $name . '.webp';
    return [true, '', [
        'src' => $rel,
        'width' => $baseW,
        'height' => $baseH,
    ]];
}
