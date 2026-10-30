<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$hid = (int) $user['hostel_id'];
$h = hostel_get($hid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user['is_owner']) {
        forbidden('Only the warden who registered the hostel can publish it.');
    }
    $err = hostel_publish($hid);
    flash($err ? 'err' : 'ok', $err ?: 'Your hostel is live. Print the QR code and share it with students.');
    redirect($err ? 'warden/setup.php' : 'warden/qr.php');
}
$steps = hostel_checklist($hid);
$links = ['blocks' => 'warden/hostel.php', 'categories' => 'warden/categories.php', 'form' => 'warden/form.php', 'providers' => 'warden/staff.php'];
$ready = true;
foreach ($steps as $s) {
    if ($s['required'] && !$s['done']) {
        $ready = false;
    }
}
page_header('Set up your hostel', $user);
?>
<h1>Set up <?= e($h['name']) ?></h1>
<p class="muted">Decide how complaints are collected, then publish. Students cannot see your hostel until you do.</p>
<div class="card">
  <ol class="checklist">
  <?php foreach ($steps as $k => $s): ?>
    <li class="<?= $s['done'] ? 'done' : '' ?>">
      <span><?= $s['done'] ? '&#10003;' : '&#9675;' ?></span>
      <a href="<?= e(url($links[$k])) ?>"><?= e($s['label']) ?></a>
      <small class="muted"><?= $s['required'] ? '(required)' : '(optional)' ?></small>
    </li>
  <?php endforeach; ?>
  </ol>
  <?php if ($h['status'] === 'active'): ?>
    <p>Your hostel is live. <a href="<?= e(url('warden/qr.php')) ?>">Open the QR code</a>.</p>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?>
      <button class="btn" type="submit" <?= $ready ? '' : 'disabled' ?>>Publish and get QR code</button>
      <?php if (!$ready): ?><small class="muted">Finish the required steps first.</small><?php endif; ?>
    </form>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
