<?php
require __DIR__ . '/../../includes/bootstrap.php';

/*
 * Reached from the email sent to the administrator. The secret in the link is the permission to decide, so the
 * administrator does not need to sign in first. The page only shows details; nothing changes until a button is
 * pressed (a POST), so email scanners that open links cannot approve or reject anything.
 */
$token = get('t') ?: post('t');
$h = hostel_find_by_token('decision', $token);
$result = null;
if ($h && $h['status'] === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $accept = post('action') === 'accept';
    $adminId = ($u = current_user()) && $u['role'] === 'admin' ? (int) $u['id'] : null;
    $err = hostel_decide((int) $h['id'], $accept, $adminId, post('note'));
    $result = $err ? ['err', $err] : ['ok', $accept ? 'accepted' : 'rejected'];
    $h = hostel_get((int) $h['id']);
}
$open = $h && $h['status'] === 'pending';
page_header('Hostel request', null);
?>
<div class="card narrow">
<?php if ($result && $result[0] === 'ok'): ?>
  <h1>Request <?= e($result[1]) ?></h1>
  <p><?= $result[1] === 'accepted' ? 'The warden was emailed a link to set up the hostel.' : 'The warden was emailed.' ?></p>
  <p class="muted">You can close this page.</p>
<?php elseif (!$h || !$open): ?>
  <h1>Link not valid</h1>
  <p class="muted">This request was already decided, or the link has expired. Sign in to see the current requests.</p>
  <a class="btn ghost" href="<?= e(url('admin/requests.php')) ?>">Go to requests</a>
<?php else: ?>
  <h1>Hostel request</h1>
  <?php if ($result): ?><div class="flash flash-err"><?= e($result[1]) ?></div><?php endif; ?>
  <h2><?= e($h['name']) ?></h2>
  <dl class="facts">
    <dt>Type</dt><dd><?= e(hostel_types()[$h['type']] ?? '') ?></dd>
    <dt>Address</dt><dd><?= e($h['address'] . ', ' . $h['city'] . ', ' . $h['state'] . ' ' . $h['pincode']) ?></dd>
    <dt>Size</dt><dd><?= (int) $h['total_blocks'] ?> blocks, <?= (int) $h['total_rooms'] ?> rooms, about <?= (int) $h['resident_count'] ?> residents</dd>
    <dt>Hostel phone</dt><dd><?= e($h['hostel_phone'] ?: '-') ?></dd>
    <dt>Requested by</dt><dd><?= e($h['owner_name'] . ', ' . $h['owner_designation']) ?></dd>
    <dt>Email</dt><dd><?= e($h['owner_email']) ?> (confirmed)</dd>
    <dt>Mobile</dt><dd><?= e($h['owner_phone']) ?></dd>
  </dl>
  <form method="post" class="stack" style="margin-top:1rem">
    <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
    <label>Note to the warden (optional)<input name="note" maxlength="500"></label>
    <div class="row">
      <button class="btn" type="submit" name="action" value="accept">Accept</button>
      <button class="btn ghost" type="submit" name="action" value="reject">Reject</button>
    </div>
  </form>
<?php endif; ?>
</div>
<?php page_footer(); ?>
