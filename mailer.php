<?php
/* Minimal mailer: SMTP (AUTH LOGIN + STARTTLS/SSL) with PHP mail() fallback.
   Configured entirely from admin settings. */

function send_mail($to, $subject, $htmlBody, &$error = null) {
    $host = trim((string)setting('smtp_host'));
    $fromEmail = trim((string)setting('smtp_from')) ?: ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName = trim((string)setting('smtp_from_name')) ?: 'Gateway to Dreams';

    $headers = "MIME-Version: 1.0\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . "From: " . mb_encode_mimeheader($fromName) . " <{$fromEmail}>\r\n";

    if ($host === '') {
        // fallback to PHP mail()
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
        if (!$ok) { $error = 'mail() не сработала (нет SMTP и нет локального MTA).'; }
        return $ok;
    }

    $port = (int)(setting('smtp_port') ?: 587);
    $secure = setting('smtp_secure', 'tls');
    $user = (string)setting('smtp_user');
    $pass = (string)setting('smtp_pass');

    // relaxed TLS — нужно для localhost / self-signed сертификатов на том же сервере
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
    ]]);
    $transport = ($secure === 'ssl') ? "ssl://{$host}" : "tcp://{$host}";
    $fp = @stream_socket_client("{$transport}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $error = "SMTP: не удалось подключиться ({$errstr}). Если хост — домен за Cloudflare, укажите localhost."; return false; }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $data = '';
        while ($line = fgets($fp, 515)) { $data .= $line; if (isset($line[3]) && $line[3] === ' ') break; }
        return $data;
    };
    $cmd = function ($c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $expect = function ($resp, $code) use (&$error) {
        if (strpos($resp, (string)$code) !== 0) { $error = 'SMTP: ' . trim($resp); return false; }
        return true;
    };

    $greet = $read();
    if (!$expect($greet, 220)) { fclose($fp); return false; }
    $ehlo = "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $cmd($ehlo);

    if ($secure === 'tls') {
        if (!$expect($cmd('STARTTLS'), 220)) { fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            $error = 'SMTP: не удалось установить TLS'; fclose($fp); return false;
        }
        $cmd($ehlo);
    }

    if ($user !== '') {
        $cmd('AUTH LOGIN');
        if (!$expect($cmd(base64_encode($user)), 334)) { fclose($fp); return false; }
        if (!$expect($cmd(base64_encode($pass)), 235)) { fclose($fp); return false; }
    }

    if (!$expect($cmd("MAIL FROM:<{$fromEmail}>"), 250)) { fclose($fp); return false; }
    if (!$expect($cmd("RCPT TO:<{$to}>"), 250)) { fclose($fp); return false; }
    if (!$expect($cmd('DATA'), 354)) { fclose($fp); return false; }

    $data = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
          . "To: {$to}\r\n" . $headers . "\r\n"
          . str_replace("\r\n.", "\r\n..", $htmlBody) . "\r\n.";
    if (!$expect($cmd($data), 250)) { fclose($fp); return false; }
    $cmd('QUIT');
    fclose($fp);
    return true;
}

function verify_email_html($name, $link) {
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    return '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5eaf0;border-radius:14px;padding:30px">'
        . '<h2 style="color:#16324f;margin:0 0 12px">Подтвердите ваш email</h2>'
        . "<p style=\"color:#334;font-size:15px\">Здравствуйте, {$name}!</p>"
        . '<p style="color:#334;font-size:15px">Чтобы завершить регистрацию в Gateway to Dreams, подтвердите ваш адрес электронной почты:</p>'
        . "<p style=\"text-align:center;margin:26px 0\"><a href=\"{$link}\" style=\"background:#dc2828;color:#fff;text-decoration:none;padding:13px 28px;border-radius:10px;font-weight:700;display:inline-block\">Подтвердить email</a></p>"
        . '<p style="color:#8595a4;font-size:13px">Если кнопка не работает, откройте ссылку вручную:<br>'
        . "<a href=\"{$link}\" style=\"color:#1d4fa2\">{$link}</a></p></div>";
}
