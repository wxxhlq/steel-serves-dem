<?php
/**
 * Отправка почты для заявок.
 *
 * Если задан SMTP — письмо уходит через SMTP (работает и на Render, и на
 * любом хостинге). Если SMTP не задан — используется штатный mail(),
 * который подходит для hoster.by с настроенным ящиком отправителя.
 */

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}
require_once ROOT . '/lib/admin.php';

function mailer_settings(): array
{
    $cfg = config_load();
    return [
        'host' => trim((string)($cfg['smtp_host'] ?? '')),
        'port' => (int)($cfg['smtp_port'] ?? 465),
        'secure' => (string)($cfg['smtp_secure'] ?? 'ssl'),
        'user' => trim((string)($cfg['smtp_user'] ?? '')),
        'pass' => (string)($cfg['smtp_pass'] ?? ''),
        'from' => trim((string)($cfg['mail_from'] ?? '')),
    ];
}

function mailer_from_address(array $s): string
{
    return $s['from'] !== '' && filter_var($s['from'], FILTER_VALIDATE_EMAIL)
        ? $s['from']
        : 'no-reply@steel-serves.by';
}

function mailer_encode_header(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/** Отправка письма. Возвращает true при успехе, детали — в error_log. */
function mailer_send(string $to, string $subject, string $html, array $extraHeaders = []): bool
{
    $s = mailer_settings();
    $from = mailer_from_address($s);

    $headers = [
        'Date: ' . date('r'),
        'From: ' . mailer_encode_header('Сайт РостНержСталь') . ' <' . $from . '>',
        'To: ' . $to,
        'Subject: ' . mailer_encode_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: steel-serves',
    ];
    $headers = array_merge($headers, $extraHeaders);

    $ok = $s['host'] !== ''
        ? smtp_send($s, $from, $to, $subject, $html, $headers)
        : @mail($to, mailer_encode_header($subject), $html, implode("\r\n", $headers));

    error_log('mail: to=' . $to . ' via=' . ($s['host'] !== '' ? 'smtp:' . $s['host'] : 'mail()') . ' result=' . ($ok ? 'sent' : 'failed'));
    if (!$ok) {
        create_log_entry('mail_failed to=' . $to . ' via=' . ($s['host'] !== '' ? 'smtp' : 'mail()'));
    }
    return $ok;
}

/** Проверка SMTP-настроек: короткое соединение + AUTH, без отправки письма. */
function mailer_test_connection(): array
{
    $s = mailer_settings();
    if ($s['host'] === '') {
        return [false, 'SMTP не настроен. Укажите сервер, порт и данные доступа.'];
    }
    try {
        $fp = smtp_connect($s);
        smtp_command($fp, 'QUIT', 221);
        fclose($fp);
        return [true, 'Соединение с SMTP установлено.'];
    } catch (Throwable $e) {
        return [false, $e->getMessage()];
    }
}

/* ---------- Внутренние функции SMTP ---------- */

function smtp_read($fp): string
{
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $data .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function smtp_command($fp, string $command, int $expected): string
{
    if (fwrite($fp, $command . "\r\n") === false) {
        throw new RuntimeException('Не удалось отправить команду SMTP.');
    }
    $reply = smtp_read($fp);
    $code = (int)substr($reply, 0, 3);
    if ($code !== $expected) {
        throw new RuntimeException('SMTP ответ ' . ($code ?: '---') . ': ' . trim(substr($reply, 4)));
    }
    return $reply;
}

function smtp_connect(array $s)
{
    $host = $s['host'];
    $port = $s['port'] > 0 ? $s['port'] : 465;
    $secure = $s['secure'];
    $target = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

    $fp = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        throw new RuntimeException('Не удалось подключиться: ' . $errstr);
    }
    stream_set_timeout($fp, 15);
    smtp_read($fp); // приветствие сервера

    $ehlo = function () use ($fp, $host) {
        return smtp_command($fp, 'EHLO ' . (gethostname() ?: $host), 250);
    };

    if ($secure === 'tls') {
        $ehlo();
        smtp_command($fp, 'STARTTLS', 220);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            throw new RuntimeException('Не удалось включить STARTTLS.');
        }
        $ehlo();
    } elseif ($secure === 'none') {
        $ehlo();
    } else {
        $ehlo();
    }

    if ($s['user'] !== '') {
        smtp_command($fp, 'AUTH LOGIN', 334);
        smtp_command($fp, base64_encode($s['user']), 334);
        smtp_command($fp, base64_encode($s['pass']), 235);
    }

    return $fp;
}

function smtp_send(array $s, string $from, string $to, string $subject, string $html, array $headers): bool
{
    try {
        $fp = smtp_connect($s);
        smtp_command($fp, 'MAIL FROM:<' . $from . '>', 250);
        smtp_command($fp, 'RCPT TO:<' . $to . '>', 250);
        smtp_command($fp, 'DATA', 354);

        // Точка в начале строки экранируется согласно SMTP.
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n";
        $message = preg_replace('/^\./m', '..', $message);
        if (fwrite($fp, $message . ".\r\n") === false) {
            throw new RuntimeException('Не удалось передать письмо.');
        }
        smtp_read($fp); // ответ после DATA
        smtp_command($fp, 'QUIT', 221);
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('smtp error: ' . $e->getMessage());
        create_log_entry('smtp_failed to=' . $to . ': ' . $e->getMessage());
        return false;
    }
}
