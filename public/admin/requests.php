<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) post('id');
    $action = post('action');
    if ($action === 'accept' || $action === 'reject') {
        $err = hostel_decide($id, $action === 'accept', (int) $user['id'], post('note'));
        flash($err ? 'err' : 'ok', $err ?: ($action === 'accept' ? 'Accepted. The warden was emailed a link to set up the hostel.' : 'Rejected. The warden was emailed.'));
    } elseif ($action === 'resend') {
        $err = hostel_resend_setup($id);
        flash($err ? 'err' : 'ok', $err ?: 'A new setup link was emailed to the warden.');
    }
    redirect('admin/requests.php');
}

$pending = q_all("SELECT * FROM hostels WHERE status = 'pending' ORDER BY created_at ASC");
$waiting = q_all("SELECT * FROM hostels WHERE status = 'approved' AND owner_user_id IS NULL ORDER BY decided_at DESC");
$recent = q_all("SELECT * FROM hostels WHERE status IN ('rejected','approved','active','suspended') AND decided_at IS NOT NULL ORDER BY decided_at DESC LIMIT 10");
page_header('Requests', $user);
?>
<h1>Hostel requests</h1>
<p class="muted">Each request below came from a warden who confirmed their email. You can also decide from the link in the email.</p>

<?php if (!mail_enabled()): ?>
  <div class="flash flash-info">Email is not switched on, so wardens will not receive their emails. See the README (Email setup) or the Email page.</div>
<?php endif; ?>

<?php if (!$pending): ?>
  <div class="card"><?= empty_state('No requests are waiting for a decision.') ?></div>
<?php endif; ?>
<?php foreach ($pending as $h): ?>
<div class="card">
  <div class="row between"><h2><?= e($h['name']) ?></h2><?= hostel_badge($h['status']) ?></div>
  <dl class="facts">
    <dt>Type</dt><dd><?= e(hostel_types()[$h['type']] ?? '') ?></dd>
    <dt>Address</dt><dd><?= e($h['address'] . ', ' . $h['city'] . ', ' . $h['state'] . ' ' . $h['pincode']) ?></dd>
    <dt>Size</dt><dd><?= (int) $h['total_blocks'] ?> blocks, <?= (int) $h['total_rooms'] ?> rooms, about <?= (int) $h['resident_count'] ?> residents</dd>
    <dt>Hostel phone</dt><dd><?= e($h['hostel_phone'] ?: '-') ?></dd>
    <dt>Requested by</dt><dd><?= e($h['owner_name'] . ', ' . $h['owner_designation']) ?></dd>
    <dt>Email</dt><dd><?= e($h['owner_email']) ?> <small class="muted">(confirmed <?= e(ago($h['email_verified_at'])) ?>)</small></dd>
    <dt>Mobile</dt><dd><?= e($h['owner_phone']) ?></dd>
  </dl>
  <form method="post" class="stack" style="margin-top:1rem">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
    <label>Note to the warden (optional; included in the email, useful when rejecting)
      <input name="note" maxlength="500"></label>
    <div class="row">
      <button class="btn" type="submit" name="action" value="accept">Accept</button>
      <button class="btn ghost" type="submit" name="action" value="reject" data-confirm-click="Reject this request?">Reject</button>
    </div>
  </form>
</div>
<?php endforeach; ?>

<?php if ($waiting): ?>
<div class="card"><h2>Approved, warden has not set a password yet</h2>
  <div class="table-wrap"><table>
    <tr><th>Hostel</th><th>Warden</th><th>Approved</th><th></th></tr>
    <?php foreach ($waiting as $h): ?>
    <tr><td><?= e($h['name']) ?></td><td><?= e($h['owner_email']) ?></td><td><?= e(ago($h['decided_at'])) ?></td>
      <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
        <button class="btn small ghost" name="action" value="resend" type="submit">Send a new setup link</button></form></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<?php if ($recent): ?>
<div class="card"><h2>Recent decisions</h2>
  <div class="table-wrap"><table>
    <tr><th>Hostel</th><th>City</th><th>Status</th><th>Decided</th></tr>
    <?php foreach ($recent as $h): ?>
    <tr><td><a href="<?= e(url('admin/hostel.php?id=' . (int) $h['id'])) ?>"><?= e($h['name']) ?></a></td><td><?= e($h['city']) ?></td><td><?= hostel_badge($h['status']) ?></td><td><?= e(ago($h['decided_at'])) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
<?php page_footer(); ?>
