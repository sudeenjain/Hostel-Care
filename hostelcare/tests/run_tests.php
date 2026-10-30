<?php
declare(strict_types=1);
/**
 * Service-layer tests for the multi-hostel platform. Uses a throwaway SQLite database, never your real one.
 * Run:  php tests/run_tests.php
 */
define('HC_TESTING', true);
define('HC_SKIP_INSTALL_CHECK', true);
$tmp = sys_get_temp_dir() . '/hostelcare_test_' . bin2hex(random_bytes(4)) . '.sqlite';
putenv('HC_DB_DRIVER=sqlite');
putenv('HC_SQLITE_PATH=' . $tmp);
putenv('HC_PUBLIC_URL=https://hostels.example.org');
putenv('HC_MAIL_ENABLED=0');
require __DIR__ . '/../includes/bootstrap.php';

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "  PASS  $name\n"; } else { $fail++; echo "  FAIL  $name" . ($detail ? "  ($detail)" : '') . "\n"; }
}
function section(string $t): void { echo "\n$t\n"; }

/** Finds the one-time link in the newest queued email to $to; returns the 64-hex token or null. */
function token_from_mail(string $to, string $path): ?string
{
    $row = q_one('SELECT body_text FROM mail_outbox WHERE to_email = ? AND body_text LIKE ? ORDER BY id DESC', [strtolower($to), '%' . $path . '%']);
    return $row && preg_match('~' . preg_quote($path, '~') . '\?t=([a-f0-9]{64})~', $row['body_text'], $m) ? $m[1] : null;
}
function req(string $name, string $email, array $over = []): array
{
    return $over + [
        'name' => $name, 'type' => 'boys', 'address' => '12 College Road', 'city' => 'Mangaluru', 'state' => 'Karnataka', 'pincode' => '575001',
        'total_blocks' => '2', 'total_rooms' => '40', 'resident_count' => '80', 'hostel_phone' => '',
        'owner_name' => 'Warden ' . $name, 'owner_designation' => 'Warden', 'owner_email' => $email, 'owner_phone' => '9876543210', 'declare' => '1',
    ];
}
/** Runs the whole onboarding for one hostel and publishes it. Returns [hostelId, ownerUserId]. */
function onboard(string $name, string $email, int $adminId): array
{
    $hid = null;
    $errs = hostel_request_create(req($name, $email), $hid);
    hostel_verify_email((string) token_from_mail($email, 'hostel/verify.php'));
    hostel_decide($hid, true, $adminId);
    [$uid] = hostel_owner_setup((string) token_from_mail($email, 'hostel/setup.php'), 'password123', 'password123');
    q('UPDATE hostels SET blocks = ? WHERE id = ?', ["A\nB", $hid]);
    q('INSERT INTO categories (hostel_id, name, default_priority, sla_hours, active) VALUES (?,?,?,?,1)', [$hid, 'Plumbing', 'medium', 24]);
    hostel_publish($hid);
    return [$hid, (int) $uid];
}

echo "HostelCare multi-hostel service tests\n=====================================\n";
db_install_schema();
check('schema installs', db_installed());
check('public address comes from configuration', public_url('x.php') === 'https://hostels.example.org/x.php', public_url('x.php'));

section('Platform administrator');
$adminId = null;
$errs = create_user(['hostel_id' => null, 'login_id' => 'admin@test.local', 'name' => 'Platform Admin', 'email' => 'admin@test.local', 'password' => 'password123', 'role' => 'admin'], $adminId);
check('admin created without a hostel', $errs === [] && $adminId > 0);
check('admin cannot be attached to a hostel', create_user(['hostel_id' => 1, 'name' => 'X', 'email' => 'x@test.local', 'password' => 'password123', 'role' => 'admin']) !== []);
check('student without a hostel is rejected', create_user(['hostel_id' => null, 'login_id' => 'S1', 'name' => 'S', 'email' => 's@test.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '1']) !== []);

section('Hostel request and email confirmation');
$bad = hostel_request_create(req('', 'not-an-email', ['total_rooms' => 'abc', 'declare' => '']));
check('invalid request rejected with several messages', count($bad) >= 4, (string) count($bad));
$aId = null;
check('valid request accepted', hostel_request_create(req('Sunrise Boys Hostel', 'warden.a@test.local'), $aId) === [] && $aId > 0);
$h = hostel_get($aId);
check('request starts unverified', $h['status'] === 'unverified');
check('confirmation email queued for the warden', q_val("SELECT COUNT(*) FROM mail_outbox WHERE to_email = 'warden.a@test.local'") == 1);
check('no email to the administrator before the warden confirms', q_val("SELECT COUNT(*) FROM mail_outbox WHERE to_email = 'admin@test.local'") == 0);
check('database stores only a hash of the confirmation token', !str_contains((string) $h['verify_hash'], (string) token_from_mail('warden.a@test.local', 'hostel/verify.php')) && strlen((string) $h['verify_hash']) === 64);
check('a made-up token does not confirm', hostel_verify_email(str_repeat('a', 64)) === null);
$vt = token_from_mail('warden.a@test.local', 'hostel/verify.php');
check('confirming the email moves the request to pending', hostel_verify_email((string) $vt)['status'] === 'pending');
check('a second request from the same email is refused once pending', hostel_request_create(req('Other', 'warden.a@test.local')) !== []);
check('confirmation link cannot be used twice', hostel_verify_email((string) $vt) === null);
check('administrator emailed after confirmation', q_val("SELECT COUNT(*) FROM mail_outbox WHERE to_email = 'admin@test.local'") == 1);
$dt = token_from_mail('admin@test.local', 'admin/decision.php');
check('admin email has a decision link', $dt !== null);
check('decision token finds the pending request', hostel_find_by_token('decision', (string) $dt)['id'] == $aId);
check('a verify token is not a decision token', hostel_find_by_token('decision', (string) $vt) === null);

$uId = null;
hostel_request_create(req('Typo Hostel', 'typo@test.local'), $uId);
hostel_request_create(req('Typo Hostel Fixed', 'typo@test.local'), $uId2);
check('resubmitting an unconfirmed request replaces it', hostel_get($uId) === null && hostel_get($uId2)['name'] === 'Typo Hostel Fixed');
q('DELETE FROM hostels WHERE id = ?', [$uId2]);

section('Approval');
check('hostel cannot be published before approval', hostel_publish($aId) !== null);
check('admin accepts the request', hostel_decide($aId, true, $adminId, 'Welcome') === null && hostel_get($aId)['status'] === 'approved');
check('deciding twice is refused', hostel_decide($aId, false, $adminId) !== null && hostel_get($aId)['status'] === 'approved');
check('decision link is dead after the decision', hostel_find_by_token('decision', (string) $dt) === null);
$st = token_from_mail('warden.a@test.local', 'hostel/setup.php');
check('warden emailed a setup link on acceptance', $st !== null);
[$uid, $serr] = hostel_owner_setup((string) $st, 'short', 'short');
check('short password refused at setup', $uid === null && $serr !== []);
[$uid, $serr] = hostel_owner_setup((string) $st, 'password123', 'different123');
check('mismatched passwords refused at setup', $uid === null);
[$ownerA, $serr] = hostel_owner_setup((string) $st, 'password123', 'password123');
check('setup link creates the owner warden', $ownerA > 0 && $serr === []);
check('setup link works only once', hostel_owner_setup((string) $st, 'password123', 'password123')[0] === null);
$ownA = q_one('SELECT * FROM users WHERE id = ?', [$ownerA]);
check('owner is a warden of that hostel and flagged owner', $ownA['role'] === 'warden' && (int) $ownA['is_owner'] === 1 && (int) $ownA['hostel_id'] === $aId);

$rId = null;
hostel_request_create(req('Rejected Hostel', 'warden.r@test.local'), $rId);
hostel_verify_email((string) token_from_mail('warden.r@test.local', 'hostel/verify.php'));
check('admin rejects a request', hostel_decide($rId, false, $adminId, 'Not eligible') === null && hostel_get($rId)['status'] === 'rejected');
check('rejected warden is emailed', q_val("SELECT COUNT(*) FROM mail_outbox WHERE to_email = 'warden.r@test.local' AND subject LIKE '%request%'") >= 1);
check('rejected hostel has no setup link', token_from_mail('warden.r@test.local', 'hostel/setup.php') === null);

section('Configuring and publishing');
$ck = hostel_checklist($aId);
check('blocks and categories are required, form and providers optional', $ck['blocks']['required'] && $ck['categories']['required'] && !$ck['form']['required'] && !$ck['providers']['required']);
check('publishing refused while required steps are missing', hostel_publish($aId) !== null);
[$text, $perr] = hostel_parse_blocks("A, B\nC\nA");
check('blocks are cleaned and de-duplicated', $text === "A\nB\nC" && $perr === null);
q('UPDATE hostels SET blocks = ? WHERE id = ?', [$text, $aId]);
check('still refused without a category', hostel_publish($aId) !== null);
q('INSERT INTO categories (hostel_id, name, default_priority, sla_hours, active) VALUES (?,?,?,?,1)', [$aId, 'Plumbing', 'medium', 24]);
q('INSERT INTO categories (hostel_id, name, default_priority, sla_hours, active) VALUES (?,?,?,?,1)', [$aId, 'Electrical', 'high', 12]);
check('dropdown with one option refused', form_field_save($aId, ['label' => 'Where?', 'field_type' => 'dropdown', 'options' => 'Only one']) !== null);
check('dropdown with options accepted', form_field_save($aId, ['label' => 'Inside the room?', 'field_type' => 'dropdown', 'options' => "Yes\nNo", 'is_required' => 1]) === null);
check('text question accepted', form_field_save($aId, ['label' => 'Preferred time', 'field_type' => 'text']) === null);
check('unknown field type refused', form_field_save($aId, ['label' => 'X question', 'field_type' => 'script']) !== null);
$fields = form_fields($aId);
check('questions saved in order', count($fields) === 2 && $fields[0]['label'] === 'Inside the room?');
form_field_move($aId, (int) $fields[1]['id'], 'up');
check('question can be moved up', form_fields($aId)[0]['label'] === 'Preferred time');
check('publishing succeeds when ready', hostel_publish($aId) === null && hostel_get($aId)['status'] === 'active');
$qrA = hostel_get($aId)['qr_token'];
check('QR token is 32 hex characters', (bool) preg_match('/^[a-f0-9]{32}$/', (string) $qrA));
check('QR link points to the public address', hostel_qr_url(hostel_get($aId)) === 'https://hostels.example.org/h.php?c=' . $qrA);
check('QR token finds the active hostel', hostel_by_qr((string) $qrA)['id'] == $aId);
check('garbage QR token finds nothing', hostel_by_qr('../../etc/passwd') === null && hostel_by_qr(str_repeat('0', 32)) === null);
hostel_regenerate_qr($aId);
check('regenerating the QR invalidates the old code', hostel_by_qr((string) $qrA) === null && hostel_by_qr((string) hostel_get($aId)['qr_token']) !== null);

section('Second hostel (for isolation checks)');
[$bId, $ownerB] = onboard('Lakeview Girls Hostel', 'warden.b@test.local', $adminId);
check('second hostel is active with its own owner', hostel_get($bId)['status'] === 'active' && $ownerB > 0 && $bId !== $aId);
check('different QR tokens', hostel_get($aId)['qr_token'] !== hostel_get($bId)['qr_token']);

section('Students');
$sa = null; $sb = null;
check('student registers in hostel A', create_user(['hostel_id' => $aId, 'login_id' => 'cs101', 'name' => 'Asha', 'email' => 'asha@test.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '101'], $sa) === []);
check('same hostel ID allowed in hostel B', create_user(['hostel_id' => $bId, 'login_id' => 'CS101', 'name' => 'Bina', 'email' => 'bina@test.local', 'password' => 'password123', 'role' => 'student', 'block' => 'B', 'room' => '202'], $sb) === []);
check('same hostel ID refused twice inside one hostel', create_user(['hostel_id' => $aId, 'login_id' => 'CS101', 'name' => 'Dup', 'email' => 'dup@test.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '1']) !== []);
check('student login by hostel ID needs the hostel context', login_attempt('CS101', 'password123') !== null);
check('login by hostel ID works inside the right hostel', login_attempt('cs101', 'password123', $aId) === null && (int) $_SESSION['user_id'] === $sa);
unset($_SESSION['user_id']);
check('the same hostel ID resolves to the other hostel\'s own student', login_attempt('CS101', 'password123', $bId) === null && (int) $_SESSION['user_id'] === $sb);
unset($_SESSION['user_id']);
create_user(['hostel_id' => $aId, 'login_id' => 'ONLYA1', 'name' => 'Only A', 'email' => 'onlya@test.local', 'password' => 'password123', 'role' => 'student', 'block' => 'A', 'room' => '5']);
check('a hostel A ID does not exist inside hostel B', login_attempt('ONLYA1', 'password123', $bId) !== null && !isset($_SESSION['user_id']));
unset($_SESSION['user_id']);
check('email login works', login_attempt('asha@test.local', 'password123') === null);
unset($_SESSION['user_id']);
for ($i = 0; $i < 5; $i++) { login_attempt('asha@test.local', 'wrong-password'); }
check('login is throttled after repeated failures', login_attempt('asha@test.local', 'password123') !== null);
q('DELETE FROM login_attempts');

$stuA = current_user_row($sa);
function current_user_row(int $id): array
{
    return q_one('SELECT u.*, h.status AS hostel_status, h.name AS hostel_name FROM users u LEFT JOIN hostels h ON h.id = u.hostel_id WHERE u.id = ?', [$id]);
}
$stuB = current_user_row($sb);
$ownA = current_user_row($ownerA);
$ownB = current_user_row($ownerB);
$admin = current_user_row($adminId);

section('Complaints');
$catA = (int) q_val("SELECT id FROM categories WHERE hostel_id = ? AND name = 'Plumbing'", [$aId]);
$catB = (int) q_val("SELECT id FROM categories WHERE hostel_id = ? AND name = 'Plumbing'", [$bId]);
$fRoom = (int) q_val("SELECT id FROM hostel_fields WHERE hostel_id = ? AND label = 'Inside the room?'", [$aId]);
$base = ['category_id' => $catA, 'title' => 'Tap is leaking', 'description' => 'The tap in the corridor leaks all night.', 'priority' => 'medium', 'block' => 'A', 'place' => 'Room 101'];
$e = [];
check('required custom question must be answered', create_complaint($stuA, $base, [], $e) === null && $e !== []);
$e = [];
check('dropdown answer must be one of the options', create_complaint($stuA, $base + ['f_' . $fRoom => 'Maybe'], [], $e) === null);
$e = [];
check('block must come from the hostel list', create_complaint($stuA, ['block' => 'Z', 'f_' . $fRoom => 'Yes'] + $base, [], $e) === null);
$e = [];
$c1 = create_complaint($stuA, $base + ['f_' . $fRoom => 'Yes'], [], $e);
check('complaint created with custom answers', $c1 > 0, implode('; ', $e));
$c1row = get_complaint($c1);
check('answers stored with the complaint', form_answers_decode($c1row['extra_json'])[0]['value'] === 'Yes');
check('location built from block and place', $c1row['location'] === 'Block A, Room 101', $c1row['location']);
check('complaint belongs to the student\'s hostel', (int) $c1row['hostel_id'] === $aId);
$e = [];
check('category of another hostel is refused', create_complaint($stuA, ['category_id' => $catB, 'f_' . $fRoom => 'Yes'] + $base, [], $e) === null);
check('warden of hostel A is notified of the new complaint', (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND complaint_id = ?', [$ownerA, $c1]) === 1);
check('warden of hostel B is not notified', (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$ownerB]) === 0);
$e = [];
$cB = create_complaint($stuB, ['category_id' => $catB, 'title' => 'Fan is noisy', 'description' => 'Ceiling fan makes a loud noise.', 'priority' => 'low', 'block' => 'B', 'place' => 'Room 202'], [], $e);
check('hostel B complaint created', $cB > 0, implode('; ', $e));

section('Tenant isolation');
check('owner A can open complaint of A', can_view_complaint($ownA, get_complaint($c1)));
check('owner of B cannot open complaint of A', !can_view_complaint($ownB, get_complaint($c1)));
check('owner of A cannot open complaint of B', !can_view_complaint($ownA, get_complaint($cB)));
check('student of B cannot open complaint of A', !can_view_complaint($stuB, get_complaint($c1)));
check('platform admin cannot open any complaint', !can_view_complaint($admin, get_complaint($c1)) && !can_view_complaint($admin, get_complaint($cB)));
[$rowsA, $totA] = list_complaints($ownA, ['status' => '']);
[$rowsB, $totB] = list_complaints($ownB, ['status' => '']);
check('queue of A holds only A complaints', $totA === 1 && (int) $rowsA[0]['id'] === $c1);
check('queue of B holds only B complaints', $totB === 1 && (int) $rowsB[0]['id'] === $cB);
check('platform admin queue is empty', list_complaints($admin, ['status' => '']) === [[], 0]);
check('categories listed per hostel', count(categories_active($aId)) === 2 && count(categories_active($bId)) === 1);
q('INSERT INTO staff (hostel_id, name, trade, phone, active) VALUES (?,?,?,?,1)', [$aId, 'Ravi', 'Plumber', '919812345678']);
q('INSERT INTO staff (hostel_id, name, trade, phone, active) VALUES (?,?,?,?,1)', [$bId, 'Suma', 'Electrician', '919812345679']);
$stA = (int) q_val('SELECT id FROM staff WHERE hostel_id = ?', [$aId]);
$stB = (int) q_val('SELECT id FROM staff WHERE hostel_id = ?', [$bId]);
check('service providers listed per hostel', count(staff_active($aId)) === 1 && staff_active($aId)[0]['name'] === 'Ravi');
check('cannot assign another hostel\'s provider', assign_staff($ownA, $c1, $stB, null) !== null);
check('warden of B cannot act on complaint of A', staff_set_status($ownB, $c1, 'acknowledged', null) !== null && add_note($ownB, $c1, 'hello there', false) !== null);
check('can assign own provider', assign_staff($ownA, $c1, $stA, 'Please fix today') === null && (int) get_complaint($c1)['assigned_staff_id'] === $stA);
check('analytics counts only own hostel', analytics($aId)['total'] === 1 && analytics($bId)['total'] === 1);
check('export holds only own hostel rows', count(export_rows($ownA, null, null)) === 1 && (int) export_rows($ownA, null, null)[0]['id'] === $c1);
check('platform admin export is empty', export_rows($admin, null, null) === []);
check('platform counts are totals only', platform_hostel_counts($aId) === ['students' => 2, 'complaints' => 1, 'open' => 1]);

section('Confidential complaints and assistant wardens');
$asst = null;
create_user(['hostel_id' => $aId, 'login_id' => 'asst@test.local', 'name' => 'Assistant', 'email' => 'asst@test.local', 'role' => 'warden', 'is_owner' => 0, 'password' => 'password123'], $asst);
$asstU = current_user_row($asst);
$e = [];
$cc = create_complaint($stuA, $base + ['f_' . $fRoom => 'No', 'is_confidential' => '1', 'title' => 'Private matter here'], [], $e);
check('confidential complaint created', $cc > 0);
check('owner can open it', can_view_complaint($ownA, get_complaint($cc)));
check('assistant warden cannot open it', !can_view_complaint($asstU, get_complaint($cc)));
$ids = array_column(list_complaints($asstU, ['status' => ''])[0], 'title', 'id');
check('assistant sees it masked in the queue', isset($ids[$cc]) && $ids[$cc] !== 'Private matter here', (string) ($ids[$cc] ?? 'missing'));
check('assistant export masks it', in_array('Private matter here', array_column(export_rows($asstU, null, null), 'title'), true) === false);
check('student can open own confidential complaint', can_view_complaint($stuA, get_complaint($cc)));

section('Suspension');
check('admin suspends hostel A', hostel_set_suspended($aId, true) === null && hostel_get($aId)['status'] === 'suspended');
check('QR of a suspended hostel shows nothing', hostel_by_qr((string) hostel_get($aId)['qr_token']) === null);
check('suspended hostel students cannot sign in', login_attempt('asha@test.local', 'password123') !== null);
unset($_SESSION['user_id']);
check('suspended hostel owner cannot sign in', login_attempt('warden.a@test.local', 'password123') !== null);
$e = [];
check('no new complaints while suspended', create_complaint(current_user_row($sa), $base + ['f_' . $fRoom => 'Yes'], [], $e) === null);
check('other hostel unaffected', login_attempt('bina@test.local', 'password123') === null);
unset($_SESSION['user_id']);
check('reactivation restores access', hostel_set_suspended($aId, false) === null && hostel_get($aId)['status'] === 'active' && login_attempt('asha@test.local', 'password123') === null);
unset($_SESSION['user_id']);

section('Mail queue');
check('mail is queued even when sending is switched off', (int) q_val("SELECT COUNT(*) FROM mail_outbox WHERE status = 'queued'") > 0);
check('flush does nothing while disabled', mail_flush(5) === ['sent' => 0, 'failed' => 0, 'retry' => 0] || !mail_enabled());

section('Cleanup');
q("INSERT INTO hostels (name, type, address, city, state, pincode, total_blocks, total_rooms, resident_count, owner_name, owner_email, owner_phone, owner_designation, status, verify_hash, verify_expires, blocks, created_at)
   VALUES ('Old','boys','addr','c','s','575001',1,1,1,'n','old@test.local','919800000000','w','unverified','x','2000-01-01 00:00:00','','2000-01-01 00:00:00')");
hostel_cleanup();
check('unconfirmed requests are removed after they expire', (int) q_val("SELECT COUNT(*) FROM hostels WHERE owner_email = 'old@test.local'") === 0);

echo "\n$pass passed, $fail failed\n";
@unlink($tmp);
exit($fail ? 1 : 0);
