<?php
declare(strict_types=1);

/*
 * Email: messages are saved in mail_outbox first, then sent over SMTP (no libraries needed).
 * A failed send never breaks the page; it is retried and shown to the platform admin.
 */

function mail_enabled(): bool
{
    return (bool) cfg('mail.enabled') && (string) cfg('mail.username') !== '';
}

function mail_from_address(): string
{
    return (string) (cfg('mail.from_email') ?: cfg('mail.username'));
}

function mail_queue(string $to, string $subject, string $text, ?string $html = null): int
{
    q(
        "INSERT INTO mail_outbox (to_email, subject, body_text, body_html, status, attempts, created_at) VALUES (?,?,?,?,'queued',0,?)",
        [strtolower(trim($to)), mb_substr($subject, 0, 240), $text, $html, now()]
    );
    $id = (int) db()->lastInsertId();
    mail_schedule_flush();
    return $id;
}

function mail_schedule_flush(): void
{
    static $scheduled = false;
    if ($scheduled || PHP_SAPI === 'cli') {
        return;
    }
    $scheduled = true;
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        ignore_user_abort(true);
        @set_time_limit(0);
        try {
            mail_flush();
        } catch (Throwable $e) {
            // Mail problems must never reach the user.
        }
    });
}

/** Sends queued emails. Returns counts. */
function mail_flush(?int $limit = null): array
{
    $result = ['sent' => 0, 'failed' => 0, 'retry' => 0];
    if (!mail_enabled()) {
        return $result;
    }
    $limit = $limit ?? (int) cfg('mail.batch', 10);
    $max = (int) cfg('mail.max_attempts', 3);
    q("UPDATE mail_outbox SET status = 'failed', last_error = 'Expired before it could be sent' WHERE status = 'queued' AND created_at < ?",
        [date('Y-m-d H:i:s', time() - 86400)]);
    q("UPDATE mail_outbox SET status = 'queued' WHERE status = 'sending' AND created_at < ?", [date('Y-m-d H:i:s', time() - 300)]);

    foreach (q_all("SELECT * FROM mail_outbox WHERE status = 'queued' ORDER BY id ASC LIMIT " . (int) $limit) as $m) {
        $claim = q("UPDATE mail_outbox SET status = 'sending', attempts = attempts + 1 WHERE id = ? AND status = 'queued'", [$m['id']]);
        if ($claim->rowCount() !== 1) {
            continue;
        }
        $attempts = (int) $m['attempts'] + 1;
        [$ok, $err, $permanent] = smtp_send($m['to_email'], $m['subject'], $m['body_text'], $m['body_html']);
        if ($ok) {
            // The text held one-time links, so it is erased as soon as the email has left.
            q("UPDATE mail_outbox SET status = 'sent', sent_at = ?, last_error = NULL, body_text = '', body_html = NULL WHERE id = ?", [now(), $m['id']]);
            $result['sent']++;
        } elseif ($permanent || $attempts >= $max) {
            q("UPDATE mail_outbox SET status = 'failed', last_error = ?, body_text = '', body_html = NULL WHERE id = ?", [mb_substr((string) $err, 0, 250), $m['id']]);
            $result['failed']++;
        } else {
            q("UPDATE mail_outbox SET status = 'queued', last_error = ? WHERE id = ?", [mb_substr((string) $err, 0, 250), $m['id']]);
            $result['retry']++;
        }
    }
    return $result;
}

function mail_counts(): array
{
    $out = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
    foreach (q_all('SELECT status, COUNT(*) AS n FROM mail_outbox GROUP BY status') as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/*  SMTP client                                                        */
/* ------------------------------------------------------------------ */

function smtp_read($fp): array
{
    $code = 0;
    $text = '';
    while (!feof($fp) && ($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        $code = (int) substr($line, 0, 3);
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
        $meta = stream_get_meta_data($fp);
        if (!empty($meta['timed_out'])) {
            break;
        }
    }
    return [$code, trim($text)];
}

/** @return string|null error message, or null if the reply code matches */
function smtp_expect($fp, array $codes, string $step): ?string
{
    [$code, $text] = smtp_read($fp);
    return in_array($code, $codes, true) ? null : "$step: server said $code " . mb_substr($text, 0, 120);
}

function smtp_cmd($fp, string $cmd, array $codes, string $step): ?string
{
    fwrite($fp, $cmd . "\r\n");
    return smtp_expect($fp, $codes, $step);
}

function smtp_header_encode(string $s): string
{
    return preg_match('/^[\x20-\x7E]*$/', $s) ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
}

function smtp_build_message(string $to, string $subject, string $text, ?string $html): string
{
    $from = mail_from_address();
    $name = smtp_header_encode((string) cfg('mail.from_name', 'HostelCare'));
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    $h = [
        'Date: ' . date('r'),
        'From: ' . $name . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . smtp_header_encode($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
    ];
    if ($html === null || $html === '') {
        $h[] = 'Content-Type: text/plain; charset=UTF-8';
        $h[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($text));
    } else {
        $b = 'hc_' . bin2hex(random_bytes(8));
        $h[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
            . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
            . "--$b--\r\n";
    }
    return implode("\r\n", $h) . "\r\n\r\n" . $body;
}

/** @return array{0:bool,1:?string,2:bool} ok, error, permanent */
function smtp_send(string $to, string $subject, string $text, ?string $html): array
{
    $host = (string) cfg('mail.host');
    $port = (int) cfg('mail.port');
    $secure = (string) cfg('mail.secure');
    $timeout = (float) cfg('mail.timeout', 10);
    $from = mail_from_address();
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid sender or recipient address', true];
    }
    $target = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return [false, "Could not connect to $host:$port ($errstr)", false];
    }
    stream_set_timeout($fp, (int) $timeout);
    try {
        if ($e = smtp_expect($fp, [220], 'greeting')) {
            return [false, $e, false];
        }
        $ehloHost = 'hostelcare.local';
        if ($e = smtp_cmd($fp, "EHLO $ehloHost", [250], 'EHLO')) {
            return [false, $e, false];
        }
        if ($secure === 'tls') {
            if ($e = smtp_cmd($fp, 'STARTTLS', [220], 'STARTTLS')) {
                return [false, $e, false];
            }
            $tlsMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') && defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $tlsMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $tlsMethod) && !@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return [false, 'Could not start an encrypted connection to the mail server', false];
            }
            if ($e = smtp_cmd($fp, "EHLO $ehloHost", [250], 'EHLO after TLS')) {
                return [false, $e, false];
            }
        }
        if ((string) cfg('mail.username') !== '') {
            if ($e = smtp_cmd($fp, 'AUTH LOGIN', [334], 'AUTH')) {
                return [false, $e, false];
            }
            if ($e = smtp_cmd($fp, base64_encode((string) cfg('mail.username')), [334], 'AUTH user')) {
                return [false, $e, false];
            }
            [$code, $txt] = (function () use ($fp) {
                fwrite($fp, base64_encode((string) cfg('mail.password')) . "\r\n");
                return smtp_read($fp);
            })();
            if ($code !== 235) {
                return [false, 'Mail login was refused (' . $code . '). Check the username and app password.', true];
            }
        }
        if ($e = smtp_cmd($fp, "MAIL FROM:<$from>", [250], 'MAIL FROM')) {
            return [false, $e, false];
        }
        [$code, $txt] = (function () use ($fp, $to) {
            fwrite($fp, "RCPT TO:<$to>\r\n");
            return smtp_read($fp);
        })();
        if ($code === 550 || $code === 551 || $code === 553 || $code === 554) {
            return [false, "Recipient refused ($code)", true];
        }
        if ($code !== 250 && $code !== 251) {
            return [false, "RCPT TO: server said $code", false];
        }
        if ($e = smtp_cmd($fp, 'DATA', [354], 'DATA')) {
            return [false, $e, false];
        }
        $message = smtp_build_message($to, $subject, $text, $html);
        $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message));
        $message = str_replace("\n", "\r\n", $message);
        fwrite($fp, $message . "\r\n.\r\n");
        if ($e = smtp_expect($fp, [250], 'message body')) {
            return [false, $e, false];
        }
        smtp_cmd($fp, 'QUIT', [221], 'QUIT');
        return [true, null, false];
    } finally {
        @fclose($fp);
    }
}
