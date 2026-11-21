<?php
/**
 * Приём заявок с формы.
 * Ответ — JSON для сайта, HTML-страница для браузера без JS.
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/lib/admin.php';
require ROOT . '/lib/mailer.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$isJson = str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

function lead_response(bool $ok, string $message, int $code = 200, array $errors = []): void
{
    global $isJson;

    if ($isJson) {
        json_out([
            'ok' => $ok,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');

    $title = $ok ? 'Заявка отправлена' : 'Заявка не отправлена';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $phone = '8 (029) 335-50-68';

    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex">';
    echo '<title>' . $safeTitle . ' — База-Металла</title>';
    echo '<link rel="icon" href="/favicon.svg" type="image/svg+xml">';
    echo '<link rel="stylesheet" href="/assets/css/fonts.css">';
    echo '<link rel="stylesheet" href="/assets/css/site.css">';
    echo '</head><body><main class="notfound"><div class="container notfound__inner">';
    echo '<p class="section-index">База-Металла</p>';
    echo '<h1 class="notfound__title" style="font-size:clamp(2.4rem,6vw,5rem)">' . $safeTitle . '</h1>';
    echo '<p class="notfound__text">' . $safeMessage . '</p>';
    echo '<div class="notfound__actions">';
    echo '<a class="btn btn--accent" href="/">На главную</a>';
    echo '<a class="btn btn--ghost" href="tel:+375293355068">Позвонить ' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</a>';
    echo '</div></div></main></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lead_response(false, 'Этот адрес принимает только отправку формы.', 405);
}

$ip = current_ip();

// Скрытое поле для ботов.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    lead_response(true, 'Спасибо! Заявка отправлена.');
}

// Не больше 6 заявок за 10 минут с одного адреса.
$rate = rate_check('lead', $ip, 6, 600);
if (!$rate['ok']) {
    $minutes = max(1, (int)ceil($rate['retry'] / 60));
    lead_response(false, 'Слишком много заявок. Попробуйте через ' . $minutes . ' мин. или позвоните нам.', 429);
}

// Время заполнения: защита от мгновенных ботов.
$ts = (int)($_POST['ts'] ?? 0);
if ($ts > 0 && time() - $ts < 2) {
    lead_response(false, 'Форма отправилась слишком быстро. Проверьте поля и попробуйте ещё раз.', 422);
}

$name = trim((string)($_POST['name'] ?? ''));
$name = preg_replace('/\s+/u', ' ', $name) ?? '';
$phoneRaw = trim((string)($_POST['phone'] ?? ''));
$phone = preg_replace('/[^0-9+()\-\s]/u', '', $phoneRaw) ?? '';
$phoneDigits = preg_replace('/\D/', '', $phone) ?? '';
$consent = isset($_POST['consent']) && (string)$_POST['consent'] !== '';

$errors = [];
if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 80) {
    $errors['name'] = 'Укажите имя — от 2 до 80 символов.';
}
if (strlen($phoneDigits) < 9 || strlen($phoneDigits) > 15) {
    $errors['phone'] = 'Укажите телефон в формате +375 (29) 000-00-00.';
}
if (!$consent) {
    $errors['consent'] = 'Отметьте согласие на обработку персональных данных.';
}

if ($errors) {
    lead_response(false, reset($errors), 422, $errors);
}

$c = content_load();
$site = $c['site'] ?? [];
$forms = $c['forms'] ?? [];
$company = (string)($site['company'] ?? 'База-Металла');
$cfg = config_load();
// Получатель заявок: отдельная настройка, не отображается на сайте.
// Если она не задана — используется контактный e-mail из контента.
$leadEmail = trim((string)($cfg['lead_email'] ?? (getenv('BM_LEAD_EMAIL') ?: '')));
$to = $leadEmail !== '' ? $leadEmail : trim((string)($site['email'] ?? ''));

$source = preg_replace('/[^a-z0-9_-]/i', '', substr((string)($_POST['source'] ?? 'site'), 0, 40)) ?: 'site';
$page = substr(trim((string)($_POST['page'] ?? '')), 0, 300);
$utm = substr(trim((string)($_POST['utm'] ?? '')), 0, 300);
$ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);

$lead = [
    'id' => bin2hex(random_bytes(4)),
    'date' => date('Y-m-d H:i:s'),
    'name' => $name,
    'phone' => $phone,
    'source' => $source,
    'page' => $page,
    'utm' => $utm,
    'ip' => $ip,
    'ua' => $ua,
    'mail' => false,
];

/* ---------- Письмо ---------- */

$host = parse_url((string)($site['domain'] ?? ''), PHP_URL_HOST) ?: 'steel-serves.by';
$sent = false;

if (getenv('BM_DISABLE_MAIL') !== '1' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $subjectText = 'Новая заявка с сайта — ' . $name;

    $rows = [
        'Имя' => $name,
        'Телефон' => $phone,
        'Источник' => $source,
        'Страница' => $page !== '' ? $page : '—',
        'UTM' => $utm !== '' ? $utm : '—',
        'Дата' => $lead['date'],
        'IP' => $ip,
    ];

    $body = '<!doctype html><html lang="ru"><body style="margin:0;padding:24px;background:#f3f5f5;font-family:Arial,Helvetica,sans-serif;color:#0b0e0f">';
    $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;margin:0 auto;background:#ffffff;border:1px solid #dfe4e5">';
    $body .= '<tr><td style="padding:22px 26px;background:#0a0c0d;color:#ffffff">';
    $body .= '<div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#b8f23c">' . htmlspecialchars($company, ENT_QUOTES, 'UTF-8') . '</div>';
    $body .= '<div style="font-size:22px;font-weight:bold;margin-top:6px">Новая заявка с сайта</div>';
    $body .= '</td></tr><tr><td style="padding:8px 26px 22px">';
    $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">';
    foreach ($rows as $label => $value) {
        $body .= '<tr>';
        $body .= '<td style="padding:11px 0;border-bottom:1px solid #eef1f2;width:120px;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#596367;vertical-align:top">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>';
        $body .= '<td style="padding:11px 0;border-bottom:1px solid #eef1f2;font-size:15px;color:#0b0e0f">' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td>';
        $body .= '</tr>';
    }
    $body .= '</table>';
    $body .= '<p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#596367">Перезвоните клиенту. Если заявка выглядит подозрительно, просто не отвечайте на неё.</p>';
    $body .= '</td></tr></table></body></html>';

    $sent = mailer_send($to, $subjectText, $body, ['Reply-To: ' . mailer_from_address(mailer_settings())]);
}

$lead['mail'] = $sent;
$stored = leads_append($lead);

if (!$stored && !$sent) {
    create_log_entry('lead ' . ($lead['id'] ?? 'unknown') . ': storage_failed');
    lead_response(false, 'Не удалось отправить заявку. Позвоните нам: +375 (29) 335-50-68.', 500);
}

$success = trim((string)($forms['success'] ?? ''));
if ($success === '') {
    $success = 'Спасибо! Заявка отправлена. Свяжемся с вами в ближайшее время.';
}

lead_response(true, $success);
