<?php
declare(strict_types=1);
/**
 * PayPal invoicing tests. PayPal is replaced by an in-memory fake, so no network and no credentials are needed.
 * Uses a throwaway SQLite database, never your real one.
 * Run:  php tests/run_paypal_tests.php
 */
define('HC_TESTING', true);
define('HC_SKIP_INSTALL_CHECK', true);
$tmp = sys_get_temp_dir() . '/hostelcare_paypal_test_' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('HC_DB_DRIVER=sqlite');
putenv('HC_SQLITE_PATH=' . $tmp);
putenv('HC_MAIL_ENABLED=0');
putenv('HC_PAYPAL_ENABLED=1');
putenv('HC_PAYPAL_CLIENT_ID=test-id');
putenv('HC_PAYPAL_CLIENT_SECRET=test-secret');
putenv('HC_PAYPAL_WEBHOOK_ID=');
putenv('HC_PAYPAL_MAX_CENTS=50000');
require __DIR__ . '/../includes/bootstrap.php';

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  PASS  $name\n"; } else { $fail++; echo "  FAIL  $name" . ($detail ? "  ($detail)" : '') . "\n"; }
}
function section(string $t): void { echo "\n$t\n"; }

/** In-memory PayPal. $fake['calls'] records every request; $fake['invoice_status'] is what GET returns. */
$fake = ['calls' => [], 'invoice_status' => 'SENT', 'fail_send' => false, 'verify' => 'SUCCESS'];
$GLOBALS['hc_paypal_http'] = function (string $method, string $url, array $headers, ?string $body) use (&$fake): array {
    $path = parse_url($url, PHP_URL_PATH);
    $fake['calls'][] = ['method' => $method, 'path' => $path, 'headers' => $headers, 'body' => $body === null ? null : (json_decode($body, true) ?? $body)];
    if ($path === '/v1/oauth2/token') {
        return [200, json_encode(['access_token' => 'tok', 'expires_in' => 3000]), null];
    }
    if ($method === 'POST' && $path === '/v2/invoicing/invoices') {
        return [201, json_encode(['rel' => 'self', 'href' => 'https://api-m.sandbox.paypal.com/v2/invoicing/invoices/INV2-FAKE-' . count($fake['calls']), 'method' => 'GET']), null];
    }
    if ($method === 'POST' && str_ends_with($path, '/send')) {
        return $fake['fail_send'] ? [422, json_encode(['name' => 'UNPROCESSABLE_ENTITY', 'message' => 'Business rule violation']), null] : [200, '{}', null];
    }
    if ($method === 'POST' && str_ends_with($path, '/cancel')) {
        return [204, '', null];
    }
    if ($method === 'POST' && $path === '/v1/notifications/verify-webhook-signature') {
        return [200, json_encode(['verification_status' => $fake['verify']]), null];
    }
    if ($method === 'GET' && str_starts_with($path, '/v2/invoicing/invoices/')) {
        return [200, json_encode(['status' => $fake['invoice_status'], 'detail' => ['metadata' => ['recipient_view_url' => 'https://www.sandbox.paypal.com/invoice/p/#FAKE']]]), null];
    }
    return [404, json_encode(['name' => 'NOT_FOUND', 'message' => 'no route ' . $path]), null];
};

db_install_schema();
$now = now();
function hostel_row(string $name): int
{
    q("INSERT INTO hostels (name, type, address, city, state, pincode, total_blocks, total_rooms, resident_count, owner_name, owner_email, owner_phone, owner_designation, status, blocks, created_at)
       VALUES (?, 'boys', '1 Test Road', 'Mangaluru', 'Karnataka', '575001', 1, 10, 20, 'Warden', 'w@example.com', '9876543210', 'Warden', 'active', 'A', ?)", [$name, now()]);
    return (int) db()->lastInsertId();
}
function student_row(int $hostelId, string $login, string $name, string $email): int
{
    q("INSERT INTO users (hostel_id, login_id, name, email, role, password_hash, created_at) VALUES (?, ?, ?, ?, 'student', 'x', ?)", [$hostelId, $login, $name, $email, now()]);
    return (int) db()->lastInsertId();
}
$h1 = hostel_row('Hostel One');
$h2 = hostel_row('Hostel Two');
$s1 = student_row($h1, 's1', 'Asha Rao Kumar', 'asha@example.com');
$s2 = student_row($h2, 's2', 'Other Student', 'other@example.com');
$due = date('Y-m-d', strtotime('+10 days'));

section('Money formatting');
check('1250 cents is 12.50', paypal_money(1250) === '12.50');
check('5 cents is 0.05', paypal_money(5) === '0.05');
check('100000 cents is 1000.00', paypal_money(100000) === '1000.00');

section('Creating an invoice');
$r = payments_create_invoice(['hostel_id' => $h1, 'student_id' => $s1, 'title' => 'November mess fee', 'amount_cents' => 1250, 'due_date' => $due]);
check('create succeeds', $r['ok'], (string) $r['error']);
check('pay link returned', ($r['pay_url'] ?? '') !== '');
$row = q_one('SELECT * FROM fee_invoices WHERE id = ?', [$r['id']]);
check('row stored as sent', $row && $row['status'] === 'sent');
check('amount stored in cents', $row && (int) $row['amount_cents'] === 1250);
check('paypal invoice id stored', $row && str_starts_with((string) $row['paypal_invoice_id'], 'INV2-FAKE-'));
$create = null;
foreach ($fake['calls'] as $c) {
    if ($c['method'] === 'POST' && $c['path'] === '/v2/invoicing/invoices') { $create = $c; }
}
check('payload amount is a string 12.50', $create && $create['body']['items'][0]['unit_amount']['value'] === '12.50');
check('payload recipient email', $create && $create['body']['primary_recipients'][0]['billing_info']['email_address'] === 'asha@example.com');
check('payload surname split', $create && $create['body']['primary_recipients'][0]['billing_info']['name']['surname'] === 'Kumar'
    && $create['body']['primary_recipients'][0]['billing_info']['name']['given_name'] === 'Asha Rao');
check('payload invoicer is the hostel', $create && $create['body']['invoicer']['name']['business_name'] === 'Hostel One');
check('idempotency header sent', $create && in_array('PayPal-Request-Id: hc-invoice-' . $r['id'], $create['headers'], true));

section('Validation (nothing may reach PayPal)');
$before = count($fake['calls']);
$bad = [
    'zero amount'        => ['amount_cents' => 0],
    'negative amount'    => ['amount_cents' => -500],
    'over safety limit'  => ['amount_cents' => 50001],
    'empty title'        => ['title' => '  '],
    'past due date'      => ['due_date' => '2020-01-01'],
    'malformed due date' => ['due_date' => '15/11/2026'],
    'student of another hostel' => ['student_id' => $s2],
    'unknown complaint'  => ['complaint_id' => 9999],
];
foreach ($bad as $label => $override) {
    $res = payments_create_invoice($override + ['hostel_id' => $h1, 'student_id' => $s1, 'title' => 'Fee', 'amount_cents' => 1000, 'due_date' => $due]);
    check("rejects $label", !$res['ok'] && $res['id'] === null, (string) ($res['error'] ?? ''));
}
check('no PayPal calls made for invalid input', count($fake['calls']) === $before);
check('no rows stored for invalid input', (int) q_val('SELECT COUNT(*) FROM fee_invoices') === 1);

section('PayPal failures');
$fake['fail_send'] = true;
$res = payments_create_invoice(['hostel_id' => $h1, 'student_id' => $s1, 'title' => 'Fine', 'amount_cents' => 500, 'due_date' => $due, 'kind' => 'fine']);
$fake['fail_send'] = false;
check('send failure reported', !$res['ok'] && str_contains((string) $res['error'], 'PayPal error 422'));
$failed = q_one("SELECT * FROM fee_invoices WHERE status = 'failed'");
check('failed row keeps the reason', $failed && str_contains((string) $failed['last_error'], 'Business rule violation'));
$GLOBALS['hc_paypal_http_saved'] = $GLOBALS['hc_paypal_http'];
$GLOBALS['hc_paypal_http'] = fn() => [0, '', 'Could not reach PayPal: timeout'];
$res = payments_create_invoice(['hostel_id' => $h1, 'student_id' => $s1, 'title' => 'Fee', 'amount_cents' => 500, 'due_date' => $due]);
$GLOBALS['hc_paypal_http'] = $GLOBALS['hc_paypal_http_saved'];
check('unreachable PayPal reported, not thrown', !$res['ok']);

section('Webhooks');
$localId = (int) $r['id'];
$pid = (string) $row['paypal_invoice_id'];
$event = json_encode(['id' => 'WH-1', 'event_type' => 'INVOICING.INVOICE.PAID', 'resource' => ['invoice' => ['id' => $pid, 'status' => 'PAID']]]);
$fake['invoice_status'] = 'SENT';
[$code] = paypal_handle_webhook([], $event);
check('forged "paid" event does not mark paid when PayPal says SENT', $code === 200 && q_val('SELECT status FROM fee_invoices WHERE id = ?', [$localId]) === 'sent');
$fake['invoice_status'] = 'PAID';
[$code] = paypal_handle_webhook([], json_encode(['id' => 'WH-2', 'event_type' => 'INVOICING.INVOICE.PAID', 'resource' => ['invoice' => ['id' => $pid]]]));
check('real paid event marks paid', $code === 200 && q_val('SELECT status FROM fee_invoices WHERE id = ?', [$localId]) === 'paid');
check('paid_at recorded', q_val('SELECT paid_at FROM fee_invoices WHERE id = ?', [$localId]) !== null);
$calls = count($fake['calls']);
[$code, $msg] = paypal_handle_webhook([], json_encode(['id' => 'WH-2', 'event_type' => 'INVOICING.INVOICE.PAID', 'resource' => ['invoice' => ['id' => $pid]]]));
check('duplicate delivery ignored', $code === 200 && $msg === 'Already handled' && count($fake['calls']) === $calls);
[$code] = paypal_handle_webhook([], 'not json');
check('garbage body rejected', $code === 400);
[$code] = paypal_handle_webhook([], json_encode(['id' => 'WH-3', 'event_type' => 'INVOICING.INVOICE.PAID', 'resource' => ['invoice' => ['id' => 'INV2-UNKNOWN']]]));
check('unknown invoice acknowledged without error', $code === 200);
[$code] = paypal_handle_webhook([], json_encode(['id' => 'WH-4', 'event_type' => 'CATALOG.PRODUCT.CREATED', 'resource' => []]));
check('unrelated event type acknowledged', $code === 200);
$fake['invoice_status'] = 'REFUNDED';
paypal_handle_webhook([], json_encode(['id' => 'WH-5', 'event_type' => 'INVOICING.INVOICE.REFUNDED', 'resource' => ['invoice' => ['id' => $pid]]]));
check('refund after paid is recorded', q_val('SELECT status FROM fee_invoices WHERE id = ?', [$localId]) === 'refunded');

section('Webhook signature (when a webhook id is configured)');
$GLOBALS['hc_config']['paypal']['webhook_id'] = 'WEBHOOK-ID';
$fake['verify'] = 'FAILURE';
[$code] = paypal_handle_webhook(['paypal-transmission-id' => 'x'], json_encode(['id' => 'WH-6', 'event_type' => 'INVOICING.INVOICE.PAID', 'resource' => ['invoice' => ['id' => $pid]]]));
check('bad signature gets 401', $code === 401);
check('bad signature event not recorded', q_val("SELECT 1 FROM paypal_events WHERE event_id = 'WH-6'") === null);
$fake['verify'] = 'SUCCESS';
[$code] = paypal_handle_webhook(['paypal-transmission-id' => 'x'], json_encode(['id' => 'WH-7', 'event_type' => 'INVOICING.INVOICE.UPDATED', 'resource' => ['invoice' => ['id' => $pid]]]));
check('good signature accepted', $code === 200);
$GLOBALS['hc_config']['paypal']['webhook_id'] = '';

section('Status, listing and summary');
$overdue = payments_create_invoice(['hostel_id' => $h1, 'student_id' => $s1, 'title' => 'Late fee', 'amount_cents' => 2000, 'due_date' => date('Y-m-d'), 'kind' => 'fine']);
q('UPDATE fee_invoices SET due_date = ? WHERE id = ?', [date('Y-m-d', strtotime('-3 days')), $overdue['id']]);
$rows = payments_list($h1);
$byId = array_column($rows, null, 'id');
check('past-due unpaid reads as overdue', $byId[$overdue['id']]['effective_status'] === 'overdue');
check('list scoped to one hostel', count(payments_list($h2)) === 0);
check('overdue filter works', count(payments_list($h1, 'overdue')) === 1);
$sum = payments_summary($h1);
check('summary overdue total', $sum['overdue_cents'] === 2000 && $sum['overdue_count'] === 1, json_encode($sum));
check('refunded invoice not counted as outstanding', $sum['outstanding_cents'] === 2000, json_encode($sum));

section('Cancelling');
$c = payments_cancel_invoice((int) $overdue['id'], $h2);
check('cannot cancel another hostel\'s invoice', !$c['ok']);
$c = payments_cancel_invoice((int) $overdue['id'], $h1);
check('cancel works', $c['ok'] && q_val('SELECT status FROM fee_invoices WHERE id = ?', [$overdue['id']]) === 'cancelled');
$c = payments_cancel_invoice((int) $overdue['id'], $h1);
check('cannot cancel twice', !$c['ok']);

section('PayPal switched off');
$GLOBALS['hc_config']['paypal']['enabled'] = false;
$res = payments_create_invoice(['hostel_id' => $h1, 'student_id' => $s1, 'title' => 'Fee', 'amount_cents' => 500, 'due_date' => $due]);
check('refuses politely when disabled', !$res['ok'] && str_contains((string) $res['error'], 'not switched on'));
[$code] = paypal_handle_webhook([], '{}');
check('webhook returns 503 when disabled', $code === 503);

@unlink($tmp);
echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
