<?php
declare(strict_types=1);
/**
 * SMS channel tests against a mock gateway. No real SMS is ever sent.
 * Run:  php tests/run_sms_tests.php
 */
define('HC_TESTING', true);
define('HC_SKIP_INSTALL_CHECK', true);
$tmp = sys_get_temp_dir() . '/hostelcare_sms_' . bin2hex(random_bytes(4));
$port = 8200 + random_int(0, 99);
$log = $tmp . '.log';
putenv('HC_DB_DRIVER=sqlite');
putenv('HC_SQLITE_PATH=' . $tmp . '.sqlite');
putenv('HC_SMS_ENABLED=1');
putenv('HC_SMS_URL=http://127.0.0.1:' . $port);
putenv('HC_SMS_USER=testuser');
putenv('HC_SMS_PASS=testpass');
putenv('MOCK_LOG=' . $log);

$nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/mock_sms_gateway.php'],
    [1 => ['file', $nullDevice, 'w'], 2 => ['file', $nullDevice, 'w']], $pipes
);
register_shutdown_function(function () use (&$server, $tmp, $log) {
    if (is_resource($server)) {
        proc_terminate($server);
    }
    foreach ([$tmp . '.sqlite', $log, $log . '.tries'] as $f) {
        @unlink($f);
    }
});
for ($i = 0; $i < 30; $i++) {
    if (@fsockopen('127.0.0.1', $port, $en, $es, 0.2)) {
        break;
    }
    usleep(100000);
}

require __DIR__ . '/../includes/bootstrap.php';

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $name . (!$ok && $detail ? "  ($detail)" : '') . "\n";
}
function sent_log(): array
{
    global $log;
    return is_file($log) ? array_map(fn($l) => json_decode($l, true), array_filter(explode("\n", (string) file_get_contents($log)))) : [];
}

echo "HostelCare SMS tests\n====================\n";
db_install_schema();

// ---- phone normalisation -------------------------------------------
$cases = [
    '9876543210' => '+919876543210', '98765 43210' => '+919876543210', '+91 98765-43210' => '+919876543210',
    '09876543210' => '+919876543210', '919876543210' => '+919876543210', '0091 9876543210' => '+919876543210',
    '+14155552671' => '+14155552671', '5876543210' => null, '12345' => null, 'abc' => null, '' => null, '98765432101' => null,
];
$allOk = true;
foreach ($cases as $in => $want) {
    if (sms_normalize_phone((string) $in) !== $want) {
        $allOk = false;
        echo "        normalize('$in') gave " . var_export(sms_normalize_phone((string) $in), true) . ", wanted " . var_export($want, true) . "\n";
    }
}
check('phone numbers are normalised and invalid ones rejected (12 cases)', $allOk);
check('mask hides the middle of a number', sms_mask('+919876543210') === '+91xxxxxx3210');

// ---- users: validation and opt-in ----------------------------------
q("INSERT INTO hostels (name, type, address, city, state, pincode, total_blocks, total_rooms, resident_count, owner_name, owner_email, owner_phone, owner_designation, status, blocks, qr_token, created_at)
   VALUES ('Test Hostel','boys','1 Road','City','State','575001',1,1,1,'Owner','owner@t.local','919000000000','Warden','active','A', ?, ?)", [bin2hex(random_bytes(16)), now()]);
$HID = (int) q_val('SELECT id FROM hostels LIMIT 1');
$e = create_user(['hostel_id' => $HID, 'login_id' => 'bad1x', 'name' => 'Bad Phone', 'email' => 'bp@t.local', 'password' => 'password123', 'role' => 'warden', 'phone' => '12345']);
check('invalid mobile number rejected at account creation', $e !== []);
$e = create_user(['hostel_id' => $HID, 'login_id' => 'bad2x', 'name' => 'No Phone', 'email' => 'np@t.local', 'password' => 'password123', 'role' => 'warden', 'sms_opt_in' => '1']);
check('opting in to SMS without a number rejected', $e !== []);

$wid = null; $s1 = null; $s2 = null; $s3 = null; $s4 = null;
create_user(['hostel_id' => $HID, 'login_id' => 'w@t.local', 'name' => 'Warden', 'email' => 'w@t.local', 'password' => 'password123', 'role' => 'warden', 'is_owner' => 1, 'phone' => '9000000001', 'sms_opt_in' => '1'], $wid);
create_user(['hostel_id' => $HID, 'login_id' => 'st1x', 'name' => 'Opted In', 'email' => 's1@t.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '1', 'phone' => '98765 43210', 'sms_opt_in' => '1'], $s1);
create_user(['hostel_id' => $HID, 'login_id' => 'st2x', 'name' => 'Not Opted', 'email' => 's2@t.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '2', 'phone' => '9876543211'], $s2);
create_user(['hostel_id' => $HID, 'login_id' => 'st3x', 'name' => 'No Phone', 'email' => 's3@t.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '3'], $s3);
create_user(['hostel_id' => $HID, 'login_id' => 'st4x', 'name' => 'Bad Number Case', 'email' => 's4@t.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '4', 'phone' => '9000005000', 'sms_opt_in' => '1'], $s4);
check('stored number is in international format', q_val('SELECT phone FROM users WHERE id = ?', [$s1]) === '+919876543210');
$warden = q_one('SELECT * FROM users WHERE id = ?', [$wid]);
$st1 = q_one('SELECT * FROM users WHERE id = ?', [$s1]);
$st2 = q_one('SELECT * FROM users WHERE id = ?', [$s2]);
$st3 = q_one('SELECT * FROM users WHERE id = ?', [$s3]);
$st4 = q_one('SELECT * FROM users WHERE id = ?', [$s4]);

q("INSERT INTO categories (hostel_id, name, default_priority, sla_hours, active) VALUES (?, 'Plumbing','high',24,1)", [$HID]);
$cat = (int) q_val("SELECT id FROM categories WHERE name = 'Plumbing'");
$form = ['category_id' => $cat, 'title' => 'Secret tap problem', 'description' => 'Very private description text', 'location' => 'Room 1'];

// ---- queueing rules -------------------------------------------------
$id = create_complaint($st1, $form + ['is_confidential' => '1'], [], $err);
$n = (int) q_val('SELECT COUNT(*) FROM sms_outbox');
check('new complaint queues an SMS for the opted-in warden only', $n === 1 && q_val('SELECT user_id FROM sms_outbox') == $wid);
staff_set_status($warden, $id, 'acknowledged', null);
check('status change queues an SMS for the opted-in student', (int) q_val('SELECT COUNT(*) FROM sms_outbox WHERE user_id = ?', [$s1]) === 1);

$id2 = create_complaint($st2, $form, [], $err);
staff_set_status($warden, $id2, 'acknowledged', null);
check('student who did not opt in gets no SMS', (int) q_val('SELECT COUNT(*) FROM sms_outbox WHERE user_id = ?', [$s2]) === 0);
$id3 = create_complaint($st3, $form, [], $err);
staff_set_status($warden, $id3, 'acknowledged', null);
check('student with no number gets no SMS', (int) q_val('SELECT COUNT(*) FROM sms_outbox WHERE user_id = ?', [$s3]) === 0);
check('in-app notification still created for people without SMS', (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$s2]) >= 1);

$leak = (int) q_val("SELECT COUNT(*) FROM sms_outbox WHERE message LIKE '%Secret%' OR message LIKE '%private%'");
check('no SMS contains the complaint title or description', $leak === 0);
check('SMS carries the ticket number and status', (int) q_val("SELECT COUNT(*) FROM sms_outbox WHERE message LIKE '%HC-00001 is now Acknowledged%'") === 1);

// ---- sending --------------------------------------------------------
$r = sms_flush(50);
// Expected: the warden (opted in) is texted for each of the 3 new complaints, and student 1 for the status change.
check('queued messages are delivered to the gateway', $r['sent'] === 4 && $r['failed'] === 0, json_encode($r));
$logged = sent_log();
$to = array_count_values(array_column($logged, 'to'));
check('gateway received each message at the right number', count($logged) === 4
    && ($to['+919000000001'] ?? 0) === 3 && ($to['+919876543210'] ?? 0) === 1);
check('every text starts with the app name and stays short', count(array_filter($logged, fn($l) => str_starts_with($l['text'], 'HostelCare: ') && mb_strlen($l['text']) <= 160)) === 4);
check('rows are marked sent with a gateway id and time', (int) q_val("SELECT COUNT(*) FROM sms_outbox WHERE status = 'sent' AND gateway_id LIKE 'mock%' AND sent_at IS NOT NULL") === 4);
check('sent messages are not sent twice', sms_flush(50)['sent'] === 0 && count(sent_log()) === 4);

// ---- failures -------------------------------------------------------
$id4 = create_complaint($st4, $form, [], $err);
q('DELETE FROM sms_outbox');
staff_set_status($warden, $id4, 'acknowledged', null);
$r1 = sms_flush(); $r2 = sms_flush();
check('temporary gateway error (500) is retried, not lost', $r1['retry'] === 1 && $r2['retry'] === 1 && q_val('SELECT status FROM sms_outbox') === 'queued');
$r3 = sms_flush();
check('after the maximum attempts the message is marked failed', $r3['failed'] === 1 && q_val('SELECT status FROM sms_outbox') === 'failed' && (int) q_val('SELECT attempts FROM sms_outbox') === 3);
check('failure reason is stored for the admin', str_contains((string) q_val('SELECT last_error FROM sms_outbox'), '500'));

q('DELETE FROM sms_outbox');
q('UPDATE users SET phone = ? WHERE id = ?', ['+919000005001', $s4]);
staff_set_status($warden, $id4, 'in_progress', null);
$a = sms_flush(); $b = sms_flush();
check('a message that fails once then succeeds is delivered on retry', $a['retry'] === 1 && $b['sent'] === 1);

q('DELETE FROM sms_outbox');
q('UPDATE users SET phone = ? WHERE id = ?', ['+919000000000', $s4]);
staff_set_status($warden, $id4, 'resolved', 'Fixed the pipe');
$r = sms_flush();
check('permanent error (400) fails at once without retrying', $r['failed'] === 1 && (int) q_val('SELECT attempts FROM sms_outbox') === 1);

q('DELETE FROM sms_outbox');
q('UPDATE users SET phone = ? WHERE id = ?', ['+919876543210', $s4]);
$GLOBALS['hc_config']['sms']['password'] = 'wrong';
sms_enqueue($s4, '+919876543210', 'auth check');
$r = sms_flush();
check('wrong gateway password fails permanently (401) and is reported', $r['failed'] === 1 && str_contains((string) q_val('SELECT last_error FROM sms_outbox'), '401'));
$GLOBALS['hc_config']['sms']['password'] = 'testpass';

q('DELETE FROM sms_outbox');
$realBase = cfg('sms.base_url');
$GLOBALS['hc_config']['sms']['base_url'] = 'http://127.0.0.1:1';
sms_enqueue($s4, '+919876543210', 'offline check');
$r = sms_flush();
check('unreachable phone is treated as a temporary failure', $r['retry'] === 1 && str_contains((string) q_val('SELECT last_error FROM sms_outbox'), 'Could not reach'));
$GLOBALS['hc_config']['sms']['base_url'] = $realBase;

// ---- limits and housekeeping ---------------------------------------
q('DELETE FROM sms_outbox');
$GLOBALS['hc_config']['sms']['daily_cap'] = 1;
sms_enqueue($s1, '+919876543210', 'one');
sms_enqueue($s1, '+919876543210', 'two');
$r = sms_flush();
check('daily cap stops sending; the rest wait', $r['sent'] === 1 && $r['deferred'] === 1 && (int) q_val("SELECT COUNT(*) FROM sms_outbox WHERE status = 'queued'") === 1);
$GLOBALS['hc_config']['sms']['daily_cap'] = 90;

q('DELETE FROM sms_outbox');
q("INSERT INTO sms_outbox (user_id, phone, message, status, attempts, created_at) VALUES (?,?,?,'queued',0,?)", [$s1, '+919876543210', 'old', date('Y-m-d H:i:s', time() - 90000)]);
sms_flush();
check('messages older than a day expire instead of arriving late', q_val('SELECT status FROM sms_outbox') === 'failed');

q('DELETE FROM sms_outbox');
q("INSERT INTO sms_outbox (user_id, phone, message, status, attempts, created_at) VALUES (?,?,?,'sending',1,?)", [$s1, '+919876543210', 'stuck', date('Y-m-d H:i:s', time() - 600)]);
$r = sms_flush();
check('a send that crashed halfway is picked up again', $r['sent'] === 1);

q('DELETE FROM sms_outbox');
$GLOBALS['hc_config']['sms']['enabled'] = false;
staff_set_status($warden, $id4, 'closed', null);
sms_queue_for_user($s1, 'disabled check');
check('with SMS switched off nothing is queued', (int) q_val('SELECT COUNT(*) FROM sms_outbox') === 0 && sms_flush() === ['sent' => 0, 'failed' => 0, 'retry' => 0, 'deferred' => 0]);

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
