<?php
declare(strict_types=1);
/**
 * Email sending tests against a mock SMTP server. No real email is ever sent.
 * Run:  php tests/run_mail_tests.php
 */
define('HC_TESTING', true);
define('HC_SKIP_INSTALL_CHECK', true);
$base = sys_get_temp_dir() . '/hostelcare_mail_' . bin2hex(random_bytes(4));
$port = 2500 + random_int(0, 400);
$log = $base . '.log';
$modeFile = $base . '.mode';
file_put_contents($modeFile, 'ok');
putenv('HC_DB_DRIVER=sqlite');
putenv('HC_SQLITE_PATH=' . $base . '.sqlite');
putenv('HC_PUBLIC_URL=https://hostels.example.org');
putenv('HC_MAIL_ENABLED=1');
putenv('HC_MAIL_HOST=127.0.0.1');
putenv('HC_MAIL_PORT=' . $port);
putenv('HC_MAIL_SECURE=none');
putenv('HC_MAIL_USER=sender@test.local');
putenv('HC_MAIL_PASS=app-pass-1234');
$nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$server = proc_open([PHP_BINARY, __DIR__ . '/mock_smtp_server.php', (string) $port, $log, $modeFile], [1 => ['file', $nullDevice, 'w'], 2 => ['file', $nullDevice, 'w']], $pipes);
register_shutdown_function(function () use (&$server, $base) {
    if (is_resource($server)) {
        proc_terminate($server);
    }
    foreach (['.sqlite', '.log', '.mode'] as $x) { @unlink($base . $x); }
});
for ($i = 0; $i < 30; $i++) { if (@fsockopen('127.0.0.1', $port, $en, $es, 0.2)) { break; } usleep(100000); }
require __DIR__ . '/../includes/bootstrap.php';

$pass = 0; $fail = 0;
function check(string $n, bool $ok, string $d = ''): void { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? '  PASS  ' : '  FAIL  ') . $n . (!$ok && $d ? "  ($d)" : '') . "\n"; }
function received(): array { global $log; return is_file($log) ? array_map(fn($l) => json_decode($l, true), array_filter(explode("\n", (string) file_get_contents($log)))) : []; }

echo "HostelCare email tests\n======================\n";
db_install_schema();
check('email is enabled by configuration', mail_enabled());

$id = mail_queue('Warden@Test.Local', 'Hello subject', "Open this link:\nhttps://hostels.example.org/hostel/setup.php?t=" . str_repeat('a', 64), '<p>HTML body</p>');
check('email is queued first', q_val('SELECT status FROM mail_outbox WHERE id = ?', [$id]) === 'queued');
$r = mail_flush(10);
check('flush sends the email', $r['sent'] === 1 && $r['failed'] === 0, json_encode($r));
$got = received();
check('server received exactly one message', count($got) === 1);
check('login used the configured account and password', ($got[0]['user'] ?? '') === 'sender@test.local');
check('recipient is lower-cased', str_contains($got[0]['rcpt'] ?? '', 'warden@test.local'));
$raw = $got[0]['data'] ?? '';
check('message has plain-text and HTML parts', str_contains($raw, 'text/plain') && str_contains($raw, 'text/html'));
check('subject and sender are present', str_contains($raw, 'Subject:') && str_contains($raw, 'From:'));
$decoded = '';
if (preg_match_all('/\n\n([A-Za-z0-9+\/=\n]+)\n--/', $raw, $m)) { foreach ($m[1] as $part) { $decoded .= base64_decode($part); } }
check('one-time link arrives intact', str_contains($decoded, '/hostel/setup.php?t=' . str_repeat('a', 64)), substr($decoded, 0, 80));
$row = q_one('SELECT * FROM mail_outbox WHERE id = ?', [$id]);
check('sent email is marked sent', $row['status'] === 'sent');
check('text with the one-time link is erased after sending', $row['body_text'] === '' && ($row['body_html'] ?? '') === '', (string) $row['body_text']);

echo "\n";
file_put_contents($modeFile, 'badauth');
$id2 = mail_queue('a@test.local', 'Will fail login', 'x');
$r = mail_flush(10);
check('refused login is not retried forever', $r['failed'] === 1, json_encode($r));
check('refusal is recorded for the admin', str_contains((string) q_val('SELECT last_error FROM mail_outbox WHERE id = ?', [$id2]), 'refused'));

file_put_contents($modeFile, 'badrcpt');
$id3 = mail_queue('nobody@test.local', 'Bad recipient', 'x');
$r = mail_flush(10);
check('refused recipient fails permanently', $r['failed'] === 1 && q_val('SELECT status FROM mail_outbox WHERE id = ?', [$id3]) === 'failed');

file_put_contents($modeFile, 'ok');
if (is_resource($server)) { proc_terminate($server); }
usleep(300000);
$id4 = mail_queue('b@test.local', 'Server down', 'x');
$r1 = mail_flush(10);
check('connection failure is retried, not lost', $r1['retry'] === 1 && q_val('SELECT status FROM mail_outbox WHERE id = ?', [$id4]) === 'queued', json_encode($r1));
mail_flush(10); $r3 = mail_flush(10);
check('gives up after the maximum attempts', q_val('SELECT status FROM mail_outbox WHERE id = ?', [$id4]) === 'failed' && (int) q_val('SELECT attempts FROM mail_outbox WHERE id = ?', [$id4]) === 3, (string) q_val('SELECT attempts FROM mail_outbox WHERE id = ?', [$id4]));
$c = mail_counts();
check('counts add up', $c['sent'] === 1 && $c['failed'] === 3, json_encode($c));

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
