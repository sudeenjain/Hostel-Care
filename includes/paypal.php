<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/*  PayPal invoicing (sandbox first).                                  */
/*  Wardens bill students (fees, fines). PayPal emails the student an  */
/*  invoice with a pay link; a webhook tells us when it is paid.       */
/*  Money is stored as whole minor units (cents) to avoid rounding.    */
/*  No external libraries: plain PHP streams, like sms.php.            */
/* ------------------------------------------------------------------ */

function paypal_enabled(): bool
{
    return (bool) cfg('paypal.enabled') && cfg('paypal.client_id') !== '' && cfg('paypal.client_secret') !== '';
}

function paypal_base_url(): string
{
    return cfg('paypal.env') === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

/** "1250" -> "12.50". PayPal wants amounts as strings. */
function paypal_money(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

/**
 * Low-level HTTP call. Tests replace it by setting $GLOBALS['hc_paypal_http'] to a callable with the same signature.
 * @return array{0:int,1:string,2:?string} http status (0 = no answer), response body, network error
 */
function paypal_http(string $method, string $url, array $headers, ?string $body): array
{
    if (isset($GLOBALS['hc_paypal_http']) && is_callable($GLOBALS['hc_paypal_http'])) {
        return ($GLOBALS['hc_paypal_http'])($method, $url, $headers, $body);
    }
    $opts = [
        'method'        => $method,
        'header'        => implode("\r\n", $headers) . "\r\n",
        'timeout'       => (float) cfg('paypal.timeout', 15),
        'ignore_errors' => true,
    ];
    if ($body !== null) {
        $opts['content'] = $body;
    }
    $http_response_header = null;
    $raw = @file_get_contents($url, false, stream_context_create(['http' => $opts]));
    $code = 0;
    if (!empty($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    if ($code === 0) {
        $err = error_get_last();
        return [0, '', 'Could not reach PayPal' . ($err ? ': ' . preg_replace('/^.*?:\s*/', '', (string) $err['message']) : '')];
    }
    return [$code, (string) $raw, null];
}

/** @return array{ok:bool,status:int,data:array,error:?string} */
function paypal_request(string $method, string $path, ?array $json = null, array $extraHeaders = []): array
{
    $token = paypal_access_token();
    if ($token === null) {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'Could not sign in to PayPal. Check the client id and secret.'];
    }
    $headers = array_merge([
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ], $extraHeaders);
    [$code, $raw, $net] = paypal_http($method, paypal_base_url() . $path, $headers, $json === null ? null : json_encode($json, JSON_UNESCAPED_UNICODE));
    $data = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
    $ok = $code >= 200 && $code < 300;
    $error = null;
    if (!$ok) {
        $error = $net ?? paypal_error_text($data, $code);
    }
    return ['ok' => $ok, 'status' => $code, 'data' => is_array($data) ? $data : [], 'error' => $error];
}

function paypal_error_text(array $data, int $code): string
{
    $msg = (string) ($data['message'] ?? $data['error_description'] ?? $data['name'] ?? 'PayPal returned an error');
    $detail = $data['details'][0]['description'] ?? ($data['details'][0]['issue'] ?? '');
    return "PayPal error $code: $msg" . ($detail ? " ($detail)" : '');
}

/** OAuth2 client-credentials token, cached for the rest of the request. */
function paypal_access_token(): ?string
{
    static $cache = null;
    if ($cache !== null && $cache['expires'] > time() + 30) {
        return $cache['token'];
    }
    $auth = base64_encode(cfg('paypal.client_id') . ':' . cfg('paypal.client_secret'));
    [$code, $raw] = paypal_http('POST', paypal_base_url() . '/v1/oauth2/token', [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ], 'grant_type=client_credentials');
    $data = json_decode($raw, true);
    if ($code !== 200 || empty($data['access_token'])) {
        return null;
    }
    $cache = ['token' => (string) $data['access_token'], 'expires' => time() + (int) ($data['expires_in'] ?? 300)];
    return $cache['token'];
}

/* ---------------------------- database ---------------------------- */

/** Creates the payment tables on first use, so an existing install needs no manual migration. */
function payments_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $mysql = cfg('db.driver') === 'mysql';
    $pk = $mysql ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $int = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $dt = $mysql ? 'DATETIME' : 'TEXT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    db()->exec("CREATE TABLE IF NOT EXISTS fee_invoices (
      id $pk,
      hostel_id $int NOT NULL,
      student_id $int NOT NULL,
      complaint_id $int NULL,
      kind VARCHAR(10) NOT NULL DEFAULT 'fee',
      title VARCHAR(160) NOT NULL,
      amount_cents $int NOT NULL,
      currency VARCHAR(3) NOT NULL,
      due_date VARCHAR(10) NOT NULL,
      status VARCHAR(12) NOT NULL DEFAULT 'draft',
      paypal_invoice_id VARCHAR(60) NULL,
      pay_url VARCHAR(255) NULL,
      last_error VARCHAR(255) NULL,
      created_by $int NULL,
      created_at $dt NOT NULL,
      updated_at $dt NOT NULL,
      paid_at $dt NULL
    )$tail");
    db()->exec("CREATE TABLE IF NOT EXISTS paypal_events (
      id $pk,
      event_id VARCHAR(60) NOT NULL UNIQUE,
      event_type VARCHAR(80) NOT NULL,
      received_at $dt NOT NULL
    )$tail");
    $done = true;
}

function payments_status_labels(): array
{
    return ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'failed' => 'Failed'];
}

/** Unpaid invoices past their due date read as overdue. Computed on read, never stored. */
function payments_effective_status(array $row): string
{
    if ($row['status'] === 'sent' && $row['due_date'] < date('Y-m-d')) {
        return 'overdue';
    }
    return (string) $row['status'];
}

/* ---------------------------- invoices ---------------------------- */

/**
 * Bills one student and emails them a PayPal invoice.
 * @param array{hostel_id:int,student_id:int,title:string,amount_cents:int,due_date:string,kind?:string,complaint_id?:?int,created_by?:?int,note?:string} $in
 * @return array{ok:bool,id:?int,error:?string,pay_url:?string}
 */
function payments_create_invoice(array $in): array
{
    payments_ensure_schema();
    $fail = static fn(string $m): array => ['ok' => false, 'id' => null, 'error' => $m, 'pay_url' => null];

    if (!paypal_enabled()) {
        return $fail('PayPal is not switched on. Set HC_PAYPAL_ENABLED=1 and the sandbox credentials (see docs/PAYPAL_SETUP.md).');
    }
    $cents = (int) ($in['amount_cents'] ?? 0);
    $max = (int) cfg('paypal.max_amount_cents', 50000);
    if ($cents <= 0) {
        return $fail('The amount must be more than zero.');
    }
    if ($cents > $max) {
        return $fail('The amount is above the safety limit of ' . paypal_money($max) . ' ' . cfg('paypal.currency') . '.');
    }
    $title = trim((string) ($in['title'] ?? ''));
    if ($title === '' || mb_strlen($title) > 160) {
        return $fail('Give the invoice a title of up to 160 characters.');
    }
    $due = (string) ($in['due_date'] ?? '');
    $dueOk = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) === 1 && strtotime($due) !== false;
    if (!$dueOk || $due < date('Y-m-d')) {
        return $fail('The due date must be today or later, as YYYY-MM-DD.');
    }
    $kind = ($in['kind'] ?? 'fee') === 'fine' ? 'fine' : 'fee';

    $student = q_one(
        'SELECT u.id, u.name, u.email, h.name AS hostel_name FROM users u JOIN hostels h ON h.id = u.hostel_id
         WHERE u.id = ? AND u.hostel_id = ? AND u.role = ? AND u.active = 1',
        [(int) $in['student_id'], (int) $in['hostel_id'], 'student']
    );
    if (!$student) {
        return $fail('That student was not found in this hostel.');
    }
    $complaintId = isset($in['complaint_id']) && $in['complaint_id'] ? (int) $in['complaint_id'] : null;
    if ($complaintId !== null && !q_one('SELECT 1 FROM complaints WHERE id = ? AND hostel_id = ?', [$complaintId, (int) $in['hostel_id']])) {
        return $fail('That complaint was not found in this hostel.');
    }

    $currency = strtoupper((string) cfg('paypal.currency', 'USD'));
    $now = now();
    q(
        'INSERT INTO fee_invoices (hostel_id, student_id, complaint_id, kind, title, amount_cents, currency, due_date, status, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [(int) $in['hostel_id'], (int) $in['student_id'], $complaintId, $kind, $title, $cents, $currency, $due, 'draft', $in['created_by'] ?? null, $now, $now]
    );
    $localId = (int) db()->lastInsertId();

    $parts = preg_split('/\s+/', trim((string) $student['name'])) ?: [];
    $surname = count($parts) > 1 ? array_pop($parts) : '';
    $payload = [
        'detail' => [
            'invoice_number' => 'HC-' . (int) $in['hostel_id'] . '-' . $localId,
            'invoice_date'   => date('Y-m-d'),
            'currency_code'  => $currency,
            'note'           => mb_substr(trim((string) ($in['note'] ?? '')), 0, 400),
            'payment_term'   => ['due_date' => $due],
        ],
        'invoicer' => ['name' => ['business_name' => mb_substr((string) $student['hostel_name'], 0, 300)]],
        'primary_recipients' => [[
            'billing_info' => [
                'name'          => ['given_name' => implode(' ', $parts), 'surname' => $surname],
                'email_address' => (string) $student['email'],
            ],
        ]],
        'items' => [[
            'name'        => mb_substr($title, 0, 200),
            'quantity'    => '1',
            'unit_amount' => ['currency_code' => $currency, 'value' => paypal_money($cents)],
        ]],
        'configuration' => ['allow_tip' => false, 'tax_inclusive' => false],
    ];

    // The request id makes a retried create return the same invoice instead of billing twice.
    $created = paypal_request('POST', '/v2/invoicing/invoices', $payload, ['PayPal-Request-Id: hc-invoice-' . $localId]);
    $paypalId = $created['ok'] ? basename((string) ($created['data']['href'] ?? '')) : '';
    if ($paypalId === '') {
        payments_mark_failed($localId, $created['error'] ?? 'PayPal did not return an invoice id.');
        return $fail($created['error'] ?? 'PayPal did not return an invoice id.');
    }
    q('UPDATE fee_invoices SET paypal_invoice_id = ?, updated_at = ? WHERE id = ?', [$paypalId, now(), $localId]);

    $sent = paypal_request('POST', '/v2/invoicing/invoices/' . rawurlencode($paypalId) . '/send', ['send_to_recipient' => true, 'send_to_invoicer' => false]);
    if (!$sent['ok']) {
        payments_mark_failed($localId, $sent['error'] ?? 'PayPal could not send the invoice.');
        return $fail($sent['error'] ?? 'PayPal could not send the invoice.');
    }
    q('UPDATE fee_invoices SET status = ?, last_error = NULL, updated_at = ? WHERE id = ?', ['sent', now(), $localId]);

    $payUrl = null;
    $got = paypal_request('GET', '/v2/invoicing/invoices/' . rawurlencode($paypalId));
    if ($got['ok']) {
        $payUrl = $got['data']['detail']['metadata']['recipient_view_url'] ?? null;
        if (is_string($payUrl) && $payUrl !== '') {
            q('UPDATE fee_invoices SET pay_url = ? WHERE id = ?', [mb_substr($payUrl, 0, 255), $localId]);
        } else {
            $payUrl = null;
        }
    }
    return ['ok' => true, 'id' => $localId, 'error' => null, 'pay_url' => $payUrl];
}

function payments_mark_failed(int $localId, string $error): void
{
    q('UPDATE fee_invoices SET status = ?, last_error = ?, updated_at = ? WHERE id = ?', ['failed', mb_substr($error, 0, 255), now(), $localId]);
}

/** Maps a PayPal invoice status to ours. Anything unpaid and live counts as "sent". */
function payments_map_status(string $paypalStatus): string
{
    return match (strtoupper($paypalStatus)) {
        'PAID', 'MARKED_AS_PAID' => 'paid',
        'CANCELLED'              => 'cancelled',
        'REFUNDED', 'PARTIALLY_REFUNDED', 'MARKED_AS_REFUNDED' => 'refunded',
        'DRAFT'                  => 'draft',
        default                  => 'sent',
    };
}

/**
 * Asks PayPal for the invoice's real state and updates our row. Webhooks only trigger this, so a forged or
 * replayed webhook can never mark anything paid by itself.
 * @return array{ok:bool,status:?string,error:?string}
 */
function payments_sync_invoice(string $paypalInvoiceId): array
{
    payments_ensure_schema();
    $row = q_one('SELECT id, status FROM fee_invoices WHERE paypal_invoice_id = ?', [$paypalInvoiceId]);
    if (!$row) {
        return ['ok' => false, 'status' => null, 'error' => 'Unknown invoice'];
    }
    $got = paypal_request('GET', '/v2/invoicing/invoices/' . rawurlencode($paypalInvoiceId));
    if (!$got['ok']) {
        return ['ok' => false, 'status' => null, 'error' => $got['error']];
    }
    $status = payments_map_status((string) ($got['data']['status'] ?? ''));
    if ($status === 'paid' && $row['status'] !== 'paid') {
        q('UPDATE fee_invoices SET status = ?, paid_at = ?, updated_at = ? WHERE id = ?', ['paid', now(), now(), $row['id']]);
    } elseif ($status !== $row['status'] && $row['status'] !== 'paid') {
        q('UPDATE fee_invoices SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $row['id']]);
    } elseif ($status === 'refunded' && $row['status'] === 'paid') {
        q('UPDATE fee_invoices SET status = ?, updated_at = ? WHERE id = ?', ['refunded', now(), $row['id']]);
    }
    return ['ok' => true, 'status' => $status, 'error' => null];
}

/** Cancels an unpaid invoice at PayPal and locally. */
function payments_cancel_invoice(int $localId, int $hostelId): array
{
    payments_ensure_schema();
    $row = q_one('SELECT * FROM fee_invoices WHERE id = ? AND hostel_id = ?', [$localId, $hostelId]);
    if (!$row) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }
    if (in_array($row['status'], ['paid', 'cancelled', 'refunded'], true)) {
        return ['ok' => false, 'error' => 'This invoice is already ' . $row['status'] . '.'];
    }
    if ($row['paypal_invoice_id']) {
        $r = paypal_request('POST', '/v2/invoicing/invoices/' . rawurlencode((string) $row['paypal_invoice_id']) . '/cancel', [
            'send_to_recipient' => true, 'send_to_invoicer' => false, 'subject' => 'Invoice cancelled', 'note' => 'This invoice was cancelled by the hostel.',
        ]);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => $r['error']];
        }
    }
    q('UPDATE fee_invoices SET status = ?, updated_at = ? WHERE id = ?', ['cancelled', now(), $localId]);
    return ['ok' => true, 'error' => null];
}

/** Invoices for one hostel, newest first, with the student's name and the effective (overdue-aware) status. */
function payments_list(int $hostelId, ?string $status = null, int $limit = 100): array
{
    payments_ensure_schema();
    $rows = q_all(
        'SELECT i.*, u.name AS student_name, u.block, u.room FROM fee_invoices i JOIN users u ON u.id = i.student_id
         WHERE i.hostel_id = ? ORDER BY i.id DESC LIMIT ' . max(1, min(500, $limit)),
        [$hostelId]
    );
    foreach ($rows as &$r) {
        $r['effective_status'] = payments_effective_status($r);
    }
    unset($r);
    if ($status !== null) {
        $rows = array_values(array_filter($rows, static fn(array $r): bool => $r['effective_status'] === $status));
    }
    return $rows;
}

/** Totals by effective status for the hostel dashboard and the AI agent. */
function payments_summary(int $hostelId): array
{
    $out = ['count' => 0, 'billed_cents' => 0, 'paid_cents' => 0, 'outstanding_cents' => 0, 'overdue_cents' => 0, 'overdue_count' => 0];
    foreach (payments_list($hostelId, null, 500) as $r) {
        if (in_array($r['effective_status'], ['draft', 'failed', 'cancelled'], true)) {
            continue;
        }
        $out['count']++;
        $out['billed_cents'] += (int) $r['amount_cents'];
        if ($r['effective_status'] === 'paid') {
            $out['paid_cents'] += (int) $r['amount_cents'];
        } elseif ($r['effective_status'] !== 'refunded') {
            $out['outstanding_cents'] += (int) $r['amount_cents'];
            if ($r['effective_status'] === 'overdue') {
                $out['overdue_cents'] += (int) $r['amount_cents'];
                $out['overdue_count']++;
            }
        }
    }
    return $out;
}

/* ---------------------------- webhooks ---------------------------- */

/**
 * Checks the webhook came from PayPal, using PayPal's own verification endpoint.
 * With no webhook id configured we cannot verify, which is only acceptable because
 * payments_sync_invoice() re-reads the real invoice state from PayPal before changing anything.
 */
function paypal_verify_webhook(array $headers, string $rawBody): bool
{
    $webhookId = (string) cfg('paypal.webhook_id');
    if ($webhookId === '') {
        return true;
    }
    $h = array_change_key_case($headers, CASE_LOWER);
    $event = json_decode($rawBody, true);
    if (!is_array($event)) {
        return false;
    }
    $r = paypal_request('POST', '/v1/notifications/verify-webhook-signature', [
        'auth_algo'         => $h['paypal-auth-algo'] ?? '',
        'cert_url'          => $h['paypal-cert-url'] ?? '',
        'transmission_id'   => $h['paypal-transmission-id'] ?? '',
        'transmission_sig'  => $h['paypal-transmission-sig'] ?? '',
        'transmission_time' => $h['paypal-transmission-time'] ?? '',
        'webhook_id'        => $webhookId,
        'webhook_event'     => $event,
    ]);
    return $r['ok'] && ($r['data']['verification_status'] ?? '') === 'SUCCESS';
}

/**
 * Handles one webhook delivery. Returns [http status, short message].
 * Safe to call twice with the same event: the second call is ignored.
 */
function paypal_handle_webhook(array $headers, string $rawBody): array
{
    payments_ensure_schema();
    if (!paypal_enabled()) {
        return [503, 'PayPal is not enabled'];
    }
    if (!paypal_verify_webhook($headers, $rawBody)) {
        return [401, 'Signature check failed'];
    }
    $event = json_decode($rawBody, true);
    $eventId = is_array($event) ? (string) ($event['id'] ?? '') : '';
    $type = is_array($event) ? (string) ($event['event_type'] ?? '') : '';
    if ($eventId === '' || $type === '') {
        return [400, 'Not a PayPal event'];
    }
    if (q_val('SELECT 1 FROM paypal_events WHERE event_id = ?', [$eventId]) !== null) {
        return [200, 'Already handled'];
    }
    q('INSERT INTO paypal_events (event_id, event_type, received_at) VALUES (?, ?, ?)', [$eventId, mb_substr($type, 0, 80), now()]);

    if (str_starts_with($type, 'INVOICING.INVOICE.')) {
        $res = $event['resource'] ?? [];
        $invoiceId = (string) ($res['invoice']['id'] ?? $res['id'] ?? '');
        if ($invoiceId !== '') {
            $r = payments_sync_invoice($invoiceId);
            if (!$r['ok'] && $r['error'] !== 'Unknown invoice') {
                // Let PayPal retry this delivery later.
                q('DELETE FROM paypal_events WHERE event_id = ?', [$eventId]);
                return [500, 'Could not confirm the invoice with PayPal'];
            }
        }
    }
    return [200, 'OK'];
}
