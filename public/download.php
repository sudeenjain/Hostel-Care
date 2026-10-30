<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$att = q_one('SELECT * FROM attachments WHERE id = ?', [(int) get('id')]);
$c = $att ? get_complaint((int) $att['complaint_id']) : null;
if (!$att || !$c || !can_view_complaint($user, $c)) {
    http_response_code(404);
    exit('Not found');
}
$path = HC_ROOT . '/storage/uploads/' . basename($att['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('File missing');
}
header('Content-Type: ' . $att['mime']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $att['original_name']) . '"');
header('Cache-Control: private, max-age=3600');
readfile($path);
