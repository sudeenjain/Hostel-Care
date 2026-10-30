<?php
/* A tiny SMTP server for tests: php tests/mock_smtp_server.php PORT LOGFILE MODEFILE. Mode file holds: ok | badauth | badrcpt */
[$self, $port, $log, $modeFile] = $argv;
$srv = stream_socket_server("tcp://127.0.0.1:$port", $en, $es);
while ($c = @stream_socket_accept($srv, 600)) {
    $mode = trim((string) @file_get_contents($modeFile)) ?: 'ok';
    $w = fn(string $s) => fwrite($c, $s . "\r\n");
    $w('220 mock ESMTP');
    $data = false; $buf = ''; $user = ''; $rcpt = ''; $from = '';
    while (($line = fgets($c)) !== false) {
        $line = rtrim($line, "\r\n");
        if ($data) {
            if ($line === '.') {
                $data = false;
                file_put_contents($log, json_encode(['user' => $user, 'from' => $from, 'rcpt' => $rcpt, 'data' => $buf]) . "\n", FILE_APPEND);
                $w('250 queued');
            } else {
                $buf .= $line . "\n";
            }
            continue;
        }
        $cmd = strtoupper(substr($line, 0, 4));
        if ($cmd === 'EHLO') { $w('250 mock'); }
        elseif ($line === 'AUTH LOGIN') { $w('334 VXNlcm5hbWU6'); $user = base64_decode((string) fgets($c)); $w('334 UGFzc3dvcmQ6'); $pass = base64_decode((string) fgets($c));
            $w($mode === 'badauth' || $pass !== 'app-pass-1234' ? '535 5.7.8 bad credentials' : '235 ok'); }
        elseif ($cmd === 'MAIL') { $from = $line; $w('250 ok'); }
        elseif ($cmd === 'RCPT') { $rcpt = $line; $w($mode === 'badrcpt' ? '550 no such user' : '250 ok'); }
        elseif ($cmd === 'DATA') { $data = true; $buf = ''; $w('354 go'); }
        elseif ($cmd === 'QUIT') { $w('221 bye'); break; }
        elseif ($cmd === 'RSET' || $cmd === 'NOOP') { $w('250 ok'); }
        else { $w('502 unknown'); }
    }
    fclose($c);
}
