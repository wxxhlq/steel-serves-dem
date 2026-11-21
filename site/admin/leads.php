<?php
/**
 * Выгрузка заявок в CSV (Excel).
 */

define('ROOT', dirname(__DIR__));
require ROOT . '/lib/admin.php';
admin_session();
require_auth();

$leads = leads_load();
$filename = 'zayavki-' . date('Y-m-d') . '.csv';

/**
 * Не даём Excel исполнить формулу из имени или UTM-метки.
 * Апостроф Excel считает служебным и не показывает в ячейке.
 */
function csv_safe(string $value): string
{
    $normalized = ltrim($value, " \t\r\n\0");
    if ($normalized !== '' && strpbrk($normalized[0], '=+-@|') !== false) {
        return "'" . $value;
    }

    return $value;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// BOM, чтобы Excel правильно открыл кириллицу.
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}

fputcsv($out, ['Дата', 'Имя', 'Телефон', 'Источник', 'Страница', 'UTM', 'IP', 'Письмо отправлено'], ';', '"', '');

foreach ($leads as $lead) {
    fputcsv($out, [
        csv_safe((string)($lead['date'] ?? '')),
        csv_safe((string)($lead['name'] ?? '')),
        csv_safe((string)($lead['phone'] ?? '')),
        csv_safe((string)($lead['source'] ?? '')),
        csv_safe((string)($lead['page'] ?? '')),
        csv_safe((string)($lead['utm'] ?? '')),
        csv_safe((string)($lead['ip'] ?? '')),
        !empty($lead['mail']) ? 'да' : 'нет',
    ], ';', '"', '');
}

fclose($out);
