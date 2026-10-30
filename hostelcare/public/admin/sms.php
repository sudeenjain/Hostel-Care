<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    if (!sms_enabled()) {
        flash('err', 'SMS is not switched on. Set HC_SMS_ENABLED=1 and the gateway details first (see the README).');
    } elseif ($action === 'test') {
        $phone = $user['phone'] ? sms_normalize_phone((string) $user['phone']) : null;
        if (!$phone) {
            flash('err', 'Add your own mobile number in Profile first; the test message is sent to it.');
        } else {
            sms_enqueue((int) $user['id'], $phone, 'Test message. SMS sending works.');
            $r = sms_flush(20);
            flash($r['sent'] > 0 ? 'ok' : 'err', $r['sent'] > 0
                ? 'Test message handed to the phone. It should arrive within a few seconds.'
                : 'The phone did not accept the message. See the last error in the table below.');
        }
    } elseif ($action === 'flush') {
        $r = sms_flush(50);
        flash('ok', "Sent {$r['sent']}, failed {$r['failed']}, will retry {$r['retry']}, waiting for the daily limit {$r['deferred']}.");
    } elseif ($action === 'retry') {
        q("UPDATE sms_outbox SET status = 'queued', attempts = 0 WHERE status = 'failed' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 86400)]);
        flash('ok', 'Failed messages from the last 24 hours were queued again.');
    }
    redirect('admin/sms.php');
}

$counts = sms_counts();
$rows = q_all('SELECT * FROM sms_outbox ORDER BY id DESC LIMIT 50');
$optIns = (int) q_val('SELECT COUNT(*) FROM users WHERE sms_opt_in = 1 AND phone IS NOT NULL AND active = 1');
$sentToday = (int) q_val("SELECT COUNT(*) FROM sms_outbox WHERE status = 'sent' AND sent_at >= ?", [date('Y-m-d 00:00:00')]);
$host = parse_url((string) cfg('sms.base_url'), PHP_URL_HOST) ?: '';
page_header('SMS', $user);
?>
<h1>SMS notifications</h1>
<p class="muted">Messages are sent through the free "SMS Gateway for Android" app on your phone, using your SIM. Only people who tick the SMS box in their profile receive anything, and messages never include complaint details.</p>

<div class="tiles">
  <div class="tile <?= sms_enabled() ? '' : 'alert' ?>"><b><?= sms_enabled() ? 'On' : 'Off' ?></b><span><?= sms_enabled() ? 'Gateway ' . e($host) : 'Not switched on' ?></span></div>
  <div class="tile"><b><?= $optIns ?></b><span>People who opted in</span></div>
  <div class="tile"><b><?= $sentToday ?> / <?= (int) cfg('sms.daily_cap') ?></b><span>Sent today / daily limit</span></div>
  <div class="tile"><b><?= $counts['queued'] + $counts['sending'] ?></b><span>Waiting</span></div>
  <div class="tile <?= $counts['failed'] ? 'alert' : '' ?>"><b><?= $counts['failed'] ?></b><span>Failed</span></div>
  <div class="tile"><b><?= $counts['sent'] ?></b><span>Sent in total</span></div>
</div>

<div class="card row">
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="test"><button class="btn" type="submit">Send a test to my number</button></form>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="flush"><button class="btn ghost" type="submit">Send waiting messages now</button></form>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="retry"><button class="btn ghost" type="submit">Retry failed</button></form>
</div>

<div class="card">
  <h2>Latest messages</h2>
  <?php if (!$rows): ?>
    <?= empty_state('No SMS has been queued yet.') ?>
  <?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Time</th><th>To</th><th>Message</th><th>Status</th><th>Tries</th><th>Last error</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(dt($r['created_at'])) ?></td>
      <td><?= e(sms_mask($r['phone'])) ?></td>
      <td><?= e($r['message']) ?></td>
      <td><span class="badge <?= $r['status'] === 'sent' ? 'b-done' : ($r['status'] === 'failed' ? 'b-open' : 'b-prog') ?>"><?= e($r['status']) ?></span></td>
      <td><?= (int) $r['attempts'] ?></td>
      <td><small class="muted"><?= e($r['last_error'] ?? '') ?></small></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
