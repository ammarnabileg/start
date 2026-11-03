<?php
/**
 * إرسال الإيميلات: SMTP لو متظبط من إعدادات المنصة، وإلا mail() بتاعة الاستضافة.
 * عميل SMTP بسيط من غير مكتبات: SSL (بورت 465) أو STARTTLS (بورت 587) أو من غير تشفير، و AUTH LOGIN.
 */

class MailException extends Exception
{
}

/** إعدادات SMTP (الباسورد مفكوك التشفير) أو null لو مش متظبط */
function smtp_config(): ?array
{
    $host = trim((string)setting('smtp_host', ''));
    if ($host === '') return null;
    return [
        'host' => $host,
        'port' => (int)setting('smtp_port', '587') ?: 587,
        'secure' => (string)setting('smtp_secure', 'tls'), // ssl | tls | none
        'user' => (string)setting('smtp_user', ''),
        'pass' => decrypt_secret((string)setting('smtp_pass', '')),
        'from' => (string)setting('smtp_from', ''),
        'from_name' => (string)setting('smtp_from_name', '') ?: (string)setting('site_name', ''),
    ];
}

function mail_from_address(): string
{
    $c = smtp_config();
    if ($c && $c['from'] !== '') return $c['from'];
    if ($c && filter_var($c['user'], FILTER_VALIDATE_EMAIL)) return $c['user'];
    $host = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return (string)setting('mail_from', '') ?: 'no-reply@' . $host;
}

/** بيبعت إيميل HTML لمستلم واحد. بيرمي MailException لو فشل. */
function send_mail(string $to, string $subject, string $html, ?array $config = null): void
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new MailException(t('الإيميل مش صحيح: %s', $to));
    $c = $config ?? smtp_config();
    $from = $c && ($c['from'] ?? '') !== '' ? $c['from'] : mail_from_address();
    $name = $c['from_name'] ?? (string)setting('site_name', '');
    $enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };
    $body = chunk_split(base64_encode($html));
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'From: ' . ($name !== '' ? $enc($name) . ' ' : '') . '<' . $from . '>',
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (substr(strrchr($from, '@') ?: '@local', 1)) . '>',
    ];
    if (!$c) {
        if (!@mail($to, $enc($subject), $body, implode("\r\n", $headers))) {
            throw new MailException(t('الاستضافة رفضت الإرسال. اظبط SMTP من إعدادات المنصة.'));
        }
        return;
    }
    smtp_send($c, $from, $to, array_merge(['To: <' . $to . '>', 'Subject: ' . $enc($subject)], $headers), $body);
}

function smtp_send(array $c, string $from, string $to, array $headers, string $body): void
{
    $remote = ($c['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $c['host']]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new MailException(t('مقدرناش نوصل لسيرفر SMTP (%s): %s', $c['host'] . ':' . $c['port'], $errstr ?: $errno));
    stream_set_timeout($fp, 20);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $line, array $okCodes) use ($fp, $read) {
        if ($line !== '') fwrite($fp, $line . "\r\n");
        $resp = $read();
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            // منطبعش الباسورد في رسالة الخطأ
            throw new MailException(t('سيرفر SMTP رد: %s', trim($resp) ?: t('مفيش رد')));
        }
        return $resp;
    };
    $helo = preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    try {
        $cmd('', [220]);
        $cmd('EHLO ' . $helo, [250]);
        if ($c['secure'] === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT : 0))) {
                throw new MailException(t('فشل تشغيل التشفير (TLS) مع سيرفر SMTP.'));
            }
            $cmd('EHLO ' . $helo, [250]);
        }
        if ($c['user'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($c['user']), [334]);
            try {
                $cmd(base64_encode($c['pass']), [235]);
            } catch (MailException $e) {
                throw new MailException(t('اسم المستخدم أو الباسورد بتاع SMTP غلط.'));
            }
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        // أي سطر بيبدأ بنقطة لازم يتضاعف (قواعد SMTP)
        $msg = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace('/^\./m', '..', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body)));
        $cmd($msg . "\r\n.", [250]);
        $cmd('QUIT', [221, 250]);
    } finally {
        fclose($fp);
    }
}
