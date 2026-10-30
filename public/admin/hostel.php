<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

$id = (int) get('id');
$h = hostel_get($id);
if (!$h) {
    not_found();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    if ($action === 'suspend' || $action === 'reactivate') {
        $err = hostel_set_suspended($id, $action === 'suspend');
        flash($err ? 'err' : 'ok', $err ?: ($action === 'suspend' ? 'Hostel suspended. Its users can no longer sign in.' : 'Hostel reactivated.'));
    } elseif ($action === 'resend') {
        $err = hostel_resend_setup($id);
        flash($err ? 'err' : 'ok', $err ?: 'A new setup link was emailed to the warden.');
    }
    redirect('admin/hostel.php?id=' . $id);
}
$c = platform_hostel_counts($id);
$wardens = q_all("SELECT name, email, phone, active, is_owner FROM users WHERE hostel_id = ? AND role = 'warden' ORDER BY is_owner DESC, name", [$id]);
page_header($h['name'], $user);
?>
<div class="row between">
  <div><h1><?= e($h['name']) ?></h1><p class="muted"><?= e(hostel_types()[$h['type']] ?? '') ?> &middot; <?= e($h['city']) ?>, <?= e($h['state']) ?></p></div>
  <div><?= hostel_badge($h['status']) ?></div>
</div>
<div class="cols even">
  <div class="card">
    <h2>Details</h2>
    <dl class="facts">
      <dt>Address</dt><dd><?= e($h['address'] . ', ' . $h['city'] . ' ' . $h['pincode']) ?></dd>
      <dt>Size</dt><dd><?= (int) $h['total_blocks'] ?> blocks, <?= (int) $h['total_rooms'] ?> rooms, about <?= (int) $h['resident_count'] ?> residents</dd>
      <dt>Hostel phone</dt><dd><?= e($h['hostel_phone'] ?: '-') ?></dd>
      <dt>Requested by</dt><dd><?= e($h['owner_name'] . ', ' . $h['owner_designation']) ?></dd>
      <dt>Email</dt><dd><?= e($h['owner_email']) ?></dd>
      <dt>Mobile</dt><dd><?= e($h['owner_phone']) ?></dd>
      <dt>Requested</dt><dd><?= e(dt($h['created_at'])) ?></dd>
      <?php if ($h['decided_at']): ?><dt>Decision</dt><dd><?= e(dt($h['decided_at'])) ?><?= $h['decision_note'] ? ' &middot; ' . e($h['decision_note']) : '' ?></dd><?php endif; ?>
      <?php if ($h['published_at']): ?><dt>Published</dt><dd><?= e(dt($h['published_at'])) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card">
    <h2>Activity (totals only)</h2>
    <dl class="facts"><dt>Students</dt><dd><?= $c['students'] ?></dd><dt>Complaints</dt><dd><?= $c['complaints'] ?> (<?= $c['open'] ?> open)</dd></dl>
    <h3>Hostel staff accounts</h3>
    <?php if (!$wardens): ?><p class="muted">No warden account yet.</p><?php endif; ?>
    <ul class="notes"><?php foreach ($wardens as $w): ?><li><span><?= e($w['name']) ?><?= $w['is_owner'] ? ' <span class="tag">Owner</span>' : '' ?><br><small class="muted"><?= e($w['email']) ?></small></span><span><?= $w['active'] ? 'Active' : 'Disabled' ?></span></li><?php endforeach; ?></ul>
    <div class="row" style="margin-top:1rem">
      <?php if (in_array($h['status'], ['active', 'approved'], true)): ?>
        <form method="post" data-confirm="Suspend this hostel? Its wardens and students will be signed out and blocked."><?= csrf_field() ?><input type="hidden" name="action" value="suspend"><button class="btn ghost" type="submit">Suspend hostel</button></form>
      <?php elseif ($h['status'] === 'suspended'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reactivate"><button class="btn" type="submit">Reactivate hostel</button></form>
      <?php endif; ?>
      <?php if ($h['status'] === 'approved' && !$h['owner_user_id']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="resend"><button class="btn ghost" type="submit">Send a new setup link</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_footer(); ?>
