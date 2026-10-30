<?php
/**
 * Stand-in for the "SMS Gateway for Android" app, used only by tests. Sends nothing.
 * Behaviour by recipient number ending:
 *   ...0000  -> 400 validation error      ...5000 -> always 500
 *   ...5001  -> 500 on first try, then 202 anything else -> 202 queued
 * Requires basic auth testuser:testpass. Every accepted request is appended to MOCK_LOG.
 */
$log = getenv('MOCK_LOG') ?: sys_get_temp_dir() . '/mock_sms.log';
$tries = $log . '.tries';

header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_SERVER['REQUEST_URI'] ?? '') !== '/3rdparty/v1/messages') {
    http_response_code(404);
    exit(json_encode(['error' => 'NotFound', 'message' => 'Unknown endpoint']));
}
if (($_SERVER['PHP_AUTH_USER'] ?? '') !== 'testuser' || ($_SERVER['PHP_AUTH_PW'] ?? '') !== 'testpass') {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized', 'message' => 'Invalid credentials']));
}
$body = json_decode((string) file_get_contents('php://input'), true);
$number = $body['phoneNumbers'][0] ?? '';
$text = $body['textMessage']['text'] ?? '';
if (!is_array($body) || !preg_match('/^\+[1-9]\d{7,14}$/', (string) $number) || $text === '') {
    http_response_code(400);
    exit(json_encode(['error' => 'ValidationError', 'message' => 'Invalid request']));
}
if (str_ends_with($number, '0000')) {
    http_response_code(400);
    exit(json_encode(['error' => 'ValidationError', 'message' => 'Invalid phone number format']));
}
$seen = is_file($tries) ? json_decode((string) file_get_contents($tries), true) : [];
$seen[$number] = ($seen[$number] ?? 0) + 1;
file_put_contents($tries, json_encode($seen));
if (str_ends_with($number, '5000') || (str_ends_with($number, '5001') && $seen[$number] === 1)) {
    http_response_code(500);
    exit(json_encode(['error' => 'ServerError', 'message' => 'Device busy']));
}
file_put_contents($log, json_encode(['to' => $number, 'text' => $text]) . "\n", FILE_APPEND);
http_response_code(202);
echo json_encode(['id' => 'mock' . bin2hex(random_bytes(4)), 'status' => 'queued', 'createdAt' => date('c')]);
