<?php
/**
 * Действия админки: вход, выход, сохранение, загрузка фото,
 * восстановление копий, настройки.
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/lib/admin.php';
require ROOT . '/lib/images.php';
admin_session();

$do = (string)($_GET['do'] ?? '');
$ip = current_ip();

if (!admin_ip_allowed()) {
    http_response_code(403);
    exit('Доступ в админку ограничен.');
}

function action_csrf(): void
{
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? ''));
    if (!csrf_check($token)) {
        json_out(['ok' => false, 'message' => 'Сессия истекла. Обновите страницу и войдите снова.'], 403);
    }
}

function read_json_body(int $maxBytes = 6 * 1024 * 1024): array
{
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        json_out(['ok' => false, 'message' => 'Слишком большой объём данных. Разбейте правку на части.'], 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || $raw === '') {
        json_out(['ok' => false, 'message' => 'Пустой запрос.'], 400);
    }
    if (strlen($raw) > $maxBytes) {
        json_out(['ok' => false, 'message' => 'Слишком большой объём данных. Разбейте правку на части.'], 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_out(['ok' => false, 'message' => 'Некорректные данные запроса.'], 400);
    }
    return $data;
}

/* ---------- Вход ---------- */

if ($do === 'login') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        redirect_to('index.php');
    }
    if (!is_installed()) {
        redirect_to('setup.php');
    }
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash_set('Сессия истекла. Попробуйте войти снова.');
        redirect_to('index.php');
    }

    $state = login_state($ip);
    if ($state['blocked']) {
        $minutes = max(1, (int)ceil($state['retry'] / 60));
        flash_set('Слишком много попыток. Подождите ' . $minutes . ' мин.');
        redirect_to('index.php');
    }

    $password = (string)($_POST['password'] ?? '');
    $cfg = config_load();

    if ($password !== '' && !empty($cfg['password_hash']) && password_verify($password, (string)$cfg['password_hash'])) {
        login_clear($ip);
        session_regenerate_id(true);
        $_SESSION['bm_admin'] = (string)password_version();
        $_SESSION['bm_csrf'] = bin2hex(random_bytes(24));
        redirect_to('index.php');
    }

    login_fail($ip);
    usleep(350000);
    flash_set('Неверный пароль.');
    redirect_to('index.php');
}

/* ---------- Экспорт (только чтение) ---------- */

if ($do === 'export') {
    require_auth();
    if (!is_file(content_path())) {
        http_response_code(404);
        exit('content.json не найден');
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="content-' . date('Ymd-His') . '.json"');
    header('Cache-Control: no-store');
    readfile(content_path());
    exit;
}

/* ---------- Дальше — только авторизованные JSON-действия ---------- */

require_auth_json();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'message' => 'Метод не поддерживается.'], 405);
}

action_csrf();

if ($do === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect_to('../index.html');
}

/* ---------- Сохранение контента ---------- */

if ($do === 'save') {
    $payload = read_json_body();
    $content = $payload['content'] ?? null;
    if (!is_array($content)) {
        json_out(['ok' => false, 'message' => 'Не передан контент.'], 422);
    }

    $required = ['site', 'nav', 'hero', 'advantages', 'services', 'process', 'installment', 'stats', 'works', 'reviews', 'faq', 'contacts', 'forms', 'privacy_html', 'footer'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $content)) {
            json_out(['ok' => false, 'message' => 'В данных нет раздела «' . $key . '». Обновите страницу и попробуйте снова.'], 422);
        }
    }

    // Меню
    $nav = [];
    foreach ((array)$content['nav'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string)($item['label'] ?? ''));
        $href = trim((string)($item['href'] ?? ''));
        if ($label === '') {
            continue;
        }
        if ($href === '' || !preg_match('/^(#|\/)/', $href)) {
            $href = '#';
        }
        $nav[] = ['label' => $label, 'href' => $href];
    }
    $content['nav'] = $nav;

    // Услуги: уникальные id, обложка = первое фото
    $items = [];
    $used = [];
    foreach ((array)($content['services']['items'] ?? []) as $i => $s) {
        if (!is_array($s)) {
            continue;
        }
        $id = strtolower(trim((string)($s['id'] ?? '')));
        $id = preg_replace('/[^a-z0-9-]+/', '-', $id) ?? '';
        $id = trim($id, '-');
        if ($id === '') {
            $id = 'service-' . ($i + 1);
        }
        $base = $id;
        $n = 2;
        while (isset($used[$id])) {
            $id = $base . '-' . $n;
            $n++;
        }
        $used[$id] = true;
        $s['id'] = $id;

        unset($s['prefix'], $s['count'], $s['cover']);

        $photos = [];
        foreach ((array)($s['photos'] ?? []) as $p) {
            if (!is_array($p)) {
                continue;
            }
            $src = local_img_src((string)($p['src'] ?? ''));
            if ($src === '') {
                continue;
            }
            $photos[] = [
                'src' => $src,
                'alt' => trim((string)($p['alt'] ?? '')),
            ];
        }
        $s['photos'] = $photos;
        $s['cover_img'] = $photos ? $photos[0]['src'] : '';
        $s['alt'] = $photos ? $photos[0]['alt'] : (string)($s['alt'] ?? '');
        $s['specs'] = array_values(array_filter((array)($s['specs'] ?? []), fn($row) => is_array($row) && count($row) >= 2));
        $items[] = $s;
    }
    $content['services']['items'] = $items;

    // Работы: только записи с картинкой
    $works = [];
    foreach ((array)($content['works']['items'] ?? []) as $w) {
        if (!is_array($w)) {
            continue;
        }
        $src = local_img_src((string)($w['src'] ?? ''));
        if ($src === '') {
            continue;
        }
        $works[] = [
            'src' => $src,
            'alt' => trim((string)($w['alt'] ?? '')),
            'caption' => trim((string)($w['caption'] ?? '')),
        ];
    }
    $content['works']['items'] = $works;

    // Отзывы
    $reviews = [];
    foreach ((array)($content['reviews']['items'] ?? []) as $r) {
        if (!is_array($r)) {
            continue;
        }
        if (trim((string)($r['name'] ?? '')) === '' && trim((string)($r['text'] ?? '')) === '') {
            continue;
        }
        $reviews[] = [
            'name' => trim((string)($r['name'] ?? '')),
            'role' => trim((string)($r['role'] ?? '')),
            'text' => trim((string)($r['text'] ?? '')),
        ];
    }
    $content['reviews']['items'] = $reviews;

    // FAQ
    $faq = [];
    foreach ((array)($content['faq']['items'] ?? []) as $f) {
        if (!is_array($f)) {
            continue;
        }
        if (trim((string)($f['q'] ?? '')) === '' && trim((string)($f['a'] ?? '')) === '') {
            continue;
        }
        $faq[] = ['q' => trim((string)($f['q'] ?? '')), 'a' => trim((string)($f['a'] ?? ''))];
    }
    $content['faq']['items'] = $faq;

    // Политика: только безопасный HTML
    $content['privacy_html'] = sanitize_policy_html((string)($content['privacy_html'] ?? ''));

    [$ok, $error] = content_save($content, true);
    if (!$ok) {
        json_out(['ok' => false, 'message' => $error], 500);
    }

    json_out([
        'ok' => true,
        'message' => 'Сохранено. Сайт обновлён.',
        'time' => date('H:i:s'),
    ]);
}

/* ---------- Загрузка изображения ---------- */

if ($do === 'upload') {
    if (empty($_FILES['file'])) {
        json_out(['ok' => false, 'message' => 'Файл не выбран.'], 422);
    }
    [$ok, $error, $data] = process_upload($_FILES['file'], 'uploads');
    if (!$ok) {
        json_out(['ok' => false, 'message' => $error], 422);
    }
    json_out([
        'ok' => true,
        'src' => $data['src'],
        'width' => $data['width'],
        'height' => $data['height'],
    ]);
}

/* ---------- Восстановление копии ---------- */

if ($do === 'restore') {
    $payload = read_json_body(64 * 1024);
    $file = basename((string)($payload['file'] ?? ''));
    if (!preg_match('/^content-\d{8}-\d{6}\.json$/', $file)) {
        json_out(['ok' => false, 'message' => 'Некорректное имя копии.'], 422);
    }
    $path = ROOT . '/data/backups/' . $file;
    if (!is_file($path)) {
        json_out(['ok' => false, 'message' => 'Копия не найдена.'], 404);
    }
    $restored = json_decode((string)file_get_contents($path), true);
    if (!is_array($restored) || !isset($restored['site'])) {
        json_out(['ok' => false, 'message' => 'Файл копии повреждён.'], 422);
    }
    $required = ['site', 'nav', 'hero', 'advantages', 'services', 'process', 'installment', 'stats', 'works', 'reviews', 'faq', 'contacts', 'forms', 'privacy_html', 'footer'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $restored)) {
            json_out(['ok' => false, 'message' => 'Копия неполная: нет раздела <' . $key . '>.'], 422);
        }
    }
    $restored['privacy_html'] = sanitize_policy_html((string)($restored['privacy_html'] ?? ''));

    [$ok, $error] = content_save($restored, true);
    if (!$ok) {
        json_out(['ok' => false, 'message' => $error], 500);
    }
    json_out(['ok' => true, 'message' => 'Копия восстановлена. Сайт обновлён.', 'time' => date('H:i:s')]);
}

/* ---------- Смена пароля ---------- */

if ($do === 'password') {
    $payload = read_json_body(64 * 1024);
    $current = (string)($payload['current_password'] ?? '');
    $new = (string)($payload['new_password'] ?? '');
    $new2 = (string)($payload['new_password2'] ?? '');

    $rate = rate_check('password', $ip, 8, 900);
    if (!$rate['ok']) {
        json_out(['ok' => false, 'message' => 'Слишком много попыток. Попробуйте позже.'], 429);
    }

    $cfg = config_load();
    if (empty($cfg['password_hash']) || !password_verify($current, (string)$cfg['password_hash'])) {
        json_out(['ok' => false, 'message' => 'Текущий пароль указан неверно.'], 422);
    }
    if (mb_strlen($new, 'UTF-8') < 12) {
        json_out(['ok' => false, 'message' => 'Новый пароль должен быть не короче 12 символов.'], 422);
    }
    if (preg_match('/^(password123|qwerty123456|admin123456|steel-serves|базa-металла)$/iu', $new)) {
        json_out(['ok' => false, 'message' => 'Такой пароль слишком простой. Придумайте другой.'], 422);
    }
    if ($new !== $new2) {
        json_out(['ok' => false, 'message' => 'Новые пароли не совпадают.'], 422);
    }

    $cfg['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
    $cfg['password_version'] = time();
    if (!config_save($cfg)) {
        json_out(['ok' => false, 'message' => 'Не удалось записать data/config.php. Проверьте права.'], 500);
    }

    session_regenerate_id(true);
    $_SESSION['bm_admin'] = (string)$cfg['password_version'];
    $_SESSION['bm_csrf'] = bin2hex(random_bytes(24));
    json_out(['ok' => true, 'message' => 'Пароль изменён.', 'csrf' => $_SESSION['bm_csrf']]);
}

/* ---------- Настройки почты ---------- */

if ($do === 'settings') {
    $payload = read_json_body(64 * 1024);
    $mailFrom = trim((string)($payload['mail_from'] ?? ''));
    if ($mailFrom !== '' && !filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'message' => 'Укажите корректный e-mail отправителя.'], 422);
    }
    $leadEmail = trim((string)($payload['lead_email'] ?? ''));
    if ($leadEmail !== '' && !filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) {
        json_out(['ok' => false, 'message' => 'Укажите корректный e-mail для заявок.'], 422);
    }

    $cfg = config_load();
    $cfg['mail_from'] = $mailFrom;
    if ($leadEmail !== '') {
        $cfg['lead_email'] = $leadEmail;
    } else {
        unset($cfg['lead_email']);
    }
    if (!config_save($cfg)) {
        json_out(['ok' => false, 'message' => 'Не удалось сохранить настройки. Проверьте права на data.'], 500);
    }
    json_out(['ok' => true, 'message' => 'Настройки сохранены.']);
}

json_out(['ok' => false, 'message' => 'Неизвестное действие.'], 404);
