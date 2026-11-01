<?php
declare(strict_types=1);
/**
 * Smoke test against the real PayPal sandbox. Creates and emails one invoice, then reads it back.
 *
 *   export HC_PAYPAL_ENABLED=1 HC_PAYPAL_CLIENT_ID=... HC_PAYPAL_CLIENT_SECRET=...
 *   php tools/paypal_smoke.php buyer@example.com
 *
 * Use the email of a SANDBOX personal (buyer) account, or the invoice cannot be paid. Uses a throwaway SQLite
 * database, never your real one.
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
$email = $argv[1] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/paypal_smoke.php <sandbox-buyer-email>\n");
    exit(2);
}
define('HC_SKIP_INSTALL_CHECK', true);
$tmp = sys_get_temp_dir() . '/hostelcare_paypal_smoke_' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('HC_DB_DRIVER=sqlite');
putenv('HC_SQLITE_PATH=' . $tmp);
require __DIR__ . '/../includes/bootstrap.php';

if (!paypal_enabled()) {
    fwrite(STDERR, "PayPal is off. Set HC_PAYPAL_ENABLED=1, HC_PAYPAL_CLIENT_ID and HC_PAYPAL_CLIENT_SECRET first.\n");
    exit(2);
}
echo "Environment: " . cfg('paypal.env') . "\n";
if (paypal_access_token() === null) {
    fwrite(STDERR, "FAIL  could not get an access token. Check the client id and secret, and that they are from the Sandbox tab.\n");
    exit(1);
}
echo "PASS  got an access token\n";

db_install_schema();
$now = now();
q("INSERT INTO hostels (name, type, address, city, state, pincode, total_blocks, total_rooms, resident_count, owner_name, owner_email, owner_phone, owner_designation, status, blocks, created_at)
   VALUES ('Smoke Test Hostel', 'boys', '1 Test Road', 'Mangaluru', 'Karnataka', '575001', 1, 10, 20, 'Test Warden', 'warden@example.com', '9876543210', 'Warden', 'active', 'A', ?)", [$now]);
$hostelId = (int) db()->lastInsertId();
q("INSERT INTO users (hostel_id, login_id, name, email, role, password_hash, created_at) VALUES (?, 'smoke1', 'Sample Student', ?, 'student', 'x', ?)", [$hostelId, $email, $now]);
$studentId = (int) db()->lastInsertId();

$r = payments_create_invoice([
    'hostel_id' => $hostelId, 'student_id' => $studentId, 'title' => 'Smoke test: November mess fee',
    'amount_cents' => 1250, 'due_date' => date('Y-m-d', strtotime('+7 days')), 'note' => 'Sandbox smoke test',
]);
if (!$r['ok']) {
    fwrite(STDERR, "FAIL  create invoice: {$r['error']}\n");
    exit(1);
}
echo "PASS  invoice created and sent (local id {$r['id']})\n";
echo 'Pay link: ' . ($r['pay_url'] ?? '(not returned)') . "\n";

$row = q_one('SELECT paypal_invoice_id, status FROM fee_invoices WHERE id = ?', [$r['id']]);
$sync = payments_sync_invoice((string) $row['paypal_invoice_id']);
echo ($sync['ok'] ? 'PASS' : 'FAIL') . "  read back from PayPal: " . ($sync['status'] ?? $sync['error']) . "\n";
echo "PayPal invoice id: {$row['paypal_invoice_id']}\n";
@unlink($tmp);
exit($sync['ok'] ? 0 : 1);
