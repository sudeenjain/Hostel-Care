<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    if (!mail_enabled()) {
        flash('err', 'Email is not switched on. Set HC_MAIL_ENABLED=1 with the mail account details (see the README).');
    } elseif ($action === 'test') {
        mail_queue($user['email'], 'HostelCare test email', "This is a test email from HostelCare.\nIf you can read it, email sending works.");
        $r = mail_flush(20);
        flash($r['sent'] > 0 ? 'ok' : 'err', $r['sent'] > 0 ? 'Test email sent to ' . $user['email'] . '. Check your inbox.' : 'The mail server did not accept the message. See the last error in the table below.');
    } elseif ($action === 'flush') {
        $r = mail_flush(50);
        flash('ok', "Sent {$r['sent']}, failed {$r['failed']}, will retry {$r['retry']}.");
    }
    redirect('admin/mail.php');
}
$counts = mail_counts();
$rows = q_all('SELECT * FROM mail_outbox ORDER BY id DESC LIMIT 50');
$view = null;
if (cfg('mail.preview') && ($vid = (int) get('view'))) {
    $view = q_one('SELECT * FROM mail_outbox WHERE id = ?', [$vid]);
}
page_header('Email', $user);
?>
<h1>Email</h1>
<p class="muted">Approval, setup and confirmation emails are sent through your mail account (for free, a Gmail address with an app password). Text of sent emails is erased because it contains one-time links.</p>

<div class="tiles">
  <div class="tile <?= mail_enabled() ? '' : 'alert' ?>"><b><?= mail_enabled() ? 'On' : 'Off' ?></b><span><?= mail_enabled() ? e(cfg('mail.host')) . ' as ' . e(mail_from_address()) : 'Not switched on' ?></span></div>
  <div class="tile"><b><?= $counts['queued'] + $counts['sending'] ?></b><span>Waiting</span></div>
  <div class="tile"><b><?= $counts['sent'] ?></b><span>Sent</span></div>
  <div class="tile <?= $counts['failed'] ? 'alert' : '' ?>"><b><?= $counts['failed'] ?></b><span>Failed</span></div>
</div>
<?php if (cfg('mail.preview')): ?>
  <div class="flash flash-info"><strong>Preview mode is on (development only).</strong> The text of waiting emails can be opened below, so you can follow the links without a mail server. Turn <code>HC_MAIL_PREVIEW</code> off on a real site.</div>
<?php endif; ?>

<div class="card row">
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="test"><button class="btn" type="submit">Send a test email to me</button></form>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="flush"><button class="btn ghost" type="submit">Send waiting emails now</button></form>
</div>

<?php if ($view): ?>
<div class="card"><h2><?= e($view['subject']) ?></h2><p class="muted">To <?= e($view['to_email']) ?></p><pre class="pre" id="mail-preview"><?= e($view['body_text'] !== '' ? $view['body_text'] : '(text erased after sending)') ?></pre></div>
<?php endif; ?>

<div class="card">
  <h2>Latest emails</h2>
  <?php if (!$rows): ?>
    <?= empty_state('No email has been queued yet.') ?>
  <?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Time</th><th>To</th><th>Subject</th><th>Status</th><th>Tries</th><th>Last error</th><?= cfg('mail.preview') ? '<th></th>' : '' ?></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(dt($r['created_at'])) ?></td><td><?= e($r['to_email']) ?></td><td><?= e($r['subject']) ?></td>
      <td><span class="badge <?= $r['status'] === 'sent' ? 'b-done' : ($r['status'] === 'failed' ? 'b-open' : 'b-prog') ?>"><?= e($r['status']) ?></span></td>
      <td><?= (int) $r['attempts'] ?></td><td><small class="muted"><?= e($r['last_error'] ?? '') ?></small></td>
      <?php if (cfg('mail.preview')): ?><td><a class="btn small ghost" href="?view=<?= (int) $r['id'] ?>">Open</a></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
