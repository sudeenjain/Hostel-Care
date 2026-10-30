<?php
declare(strict_types=1);

/*
 * SMS channel. Messages are written to the sms_outbox table first, then sent to the
 * "SMS Gateway for Android" app (REST API) after the page has been prepared.
 * A failed send never breaks a complaint action; it is retried and shown to the admin.
 */

function sms_enabled(): bool
{
    return (bool) cfg('sms.enabled') && (string) cfg('sms.username') !== '';
}

/** Returns an E.164 number such as +919876543210, or null if the input is not a valid mobile number. */
function sms_normalize_phone(string $raw): ?string
{
    $p = preg_replace('/[\s\-().]/', '', trim($raw));
    if ($p === null || $p === '') {
        return null;
    }
    if (str_starts_with($p, '00')) {
        $p = '+' . substr($p, 2);
    }
    if ($p[0] === '+') {
        return preg_match('/^\+[1-9]\d{7,14}$/', $p) ? $p : null;
    }
    if (!ctype_digit($p)) {
        return null;
    }
    $cc = (string) cfg('sms.country_code', '+91');
    if ($cc === '+91') {
        if (strlen($p) === 12 && str_starts_with($p, '91')) {
            $p = substr($p, 2);
        } elseif (strlen($p) === 11 && $p[0] === '0') {
            $p = substr($p, 1);
        }
        return preg_match('/^[6-9]\d{9}$/', $p) ? '+91' . $p : null;
    }
    $p = ltrim($p, '0');
    $full = $cc . $p;
    return preg_match('/^\+[1-9]\d{7,14}$/', $full) ? $full : null;
}

function sms_mask(string $phone): string
{
    return strlen($phone) > 7 ? substr($phone, 0, 3) . str_repeat('x', strlen($phone) - 7) . substr($phone, -4) : $phone;
}

/** Called from notify(). Queues one SMS if the user opted in and has a valid number. */
function sms_queue_for_user(int $userId, string $message): void
{
    if (!sms_enabled()) {
        return;
    }
    $u = q_one('SELECT phone, sms_opt_in, active FROM users WHERE id = ?', [$userId]);
    if (!$u || !(int) $u['sms_opt_in'] || !(int) $u['active'] || empty($u['phone'])) {
        return;
    }
    $phone = sms_normalize_phone((string) $u['phone']);
    if ($phone === null) {
        return;
    }
    sms_enqueue($userId, $phone, $message);
}

function sms_enqueue(int $userId, string $phone, string $message): int
{
    q(
        "INSERT INTO sms_outbox (user_id, phone, message, status, attempts, created_at) VALUES (?,?,?,'queued',0,?)",
        [$userId, $phone, mb_substr('HostelCare: ' . $message, 0, 300), now()]
    );
    $id = (int) db()->lastInsertId();
    sms_schedule_flush();
    return $id;
}

function sms_schedule_flush(): void
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
        try {
            sms_flush();
        } catch (Throwable $e) {
            // Sending problems must never surface to the user.
        }
    });
}

function sms_url(): string
{
    return rtrim((string) cfg('sms.base_url'), '/') . '/' . ltrim((string) cfg('sms.path'), '/');
}

/** @return array{0:int,1:string,2:?string} http status (0 = no answer), response body, network error */
function sms_http_post(string $phone, string $text): array
{
    $payload = json_encode(['phoneNumbers' => [$phone], 'textMessage' => ['text' => $text]], JSON_UNESCAPED_UNICODE);
    $auth = base64_encode(cfg('sms.username') . ':' . cfg('sms.password'));
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nAuthorization: Basic $auth\r\nAccept: application/json\r\n",
        'content'       => $payload,
        'timeout'       => (float) cfg('sms.timeout', 4),
        'ignore_errors' => true,
    ]]);
    $http_response_header = null;
    $body = @file_get_contents(sms_url(), false, $ctx);
    $code = 0;
    if (!empty($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    if ($code === 0) {
        $err = error_get_last();
        return [0, '', 'Could not reach the SMS phone' . ($err ? ': ' . preg_replace('/^.*?:\s*/', '', (string) $err['message']) : '')];
    }
    return [$code, (string) $body, null];
}

/** Sends queued messages. Returns counts. Safe to call from several requests at once. */
function sms_flush(?int $limit = null): array
{
    $result = ['sent' => 0, 'failed' => 0, 'retry' => 0, 'deferred' => 0];
    if (!sms_enabled()) {
        return $result;
    }
    $limit = $limit ?? (int) cfg('sms.batch', 10);
    $max = (int) cfg('sms.max_attempts', 3);

    // Housekeeping: messages that sat too long are no longer useful; crashed sends are retried.
    q("UPDATE sms_outbox SET status = 'failed', last_error = 'Expired before it could be sent' WHERE status = 'queued' AND created_at < ?",
        [date('Y-m-d H:i:s', time() - 86400)]);
    q("UPDATE sms_outbox SET status = 'queued' WHERE status = 'sending' AND created_at < ?",
        [date('Y-m-d H:i:s', time() - 300)]);

    $sentToday = (int) q_val("SELECT COUNT(*) FROM sms_outbox WHERE status = 'sent' AND sent_at >= ?", [date('Y-m-d 00:00:00')]);
    $cap = (int) cfg('sms.daily_cap', 90);

    $rows = q_all("SELECT * FROM sms_outbox WHERE status = 'queued' ORDER BY id ASC LIMIT " . (int) $limit);
    foreach ($rows as $r) {
        if ($sentToday >= $cap) {
            $result['deferred']++;
            continue;
        }
        $claim = q("UPDATE sms_outbox SET status = 'sending', attempts = attempts + 1 WHERE id = ? AND status = 'queued'", [$r['id']]);
        if ($claim->rowCount() !== 1) {
            continue; // another request took it
        }
        $attempts = (int) $r['attempts'] + 1;
        [$code, $body, $netErr] = sms_http_post((string) $r['phone'], (string) $r['message']);

        if ($code >= 200 && $code < 300) {
            $json = json_decode($body, true);
            q("UPDATE sms_outbox SET status = 'sent', sent_at = ?, gateway_id = ?, last_error = NULL WHERE id = ?",
                [now(), is_array($json) && isset($json['id']) ? mb_substr((string) $json['id'], 0, 80) : null, $r['id']]);
            $sentToday++;
            $result['sent']++;
            continue;
        }
        $reason = $netErr ?? ('Gateway answered ' . $code . ': ' . mb_substr(trim(strip_tags($body)), 0, 150));
        $permanent = $code >= 400 && $code < 500 && $code !== 429;
        if ($permanent || $attempts >= $max) {
            q("UPDATE sms_outbox SET status = 'failed', last_error = ? WHERE id = ?", [mb_substr($reason, 0, 250), $r['id']]);
            $result['failed']++;
        } else {
            q("UPDATE sms_outbox SET status = 'queued', last_error = ? WHERE id = ?", [mb_substr($reason, 0, 250), $r['id']]);
            $result['retry']++;
        }
    }
    return $result;
}

function sms_counts(): array
{
    $out = ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
    foreach (q_all('SELECT status, COUNT(*) AS n FROM sms_outbox GROUP BY status') as $r) {
        $out[$r['status']] = (int) $r['n'];
    }
    return $out;
}
