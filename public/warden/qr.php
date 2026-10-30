<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$hid = (int) $user['hostel_id'];
$h = hostel_get($hid);
if ($h['status'] !== 'active' || !$h['qr_token']) {
    flash('err', 'Publish your hostel first to get a QR code.');
    redirect('warden/setup.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!$user['is_owner']) {
        forbidden('Only the owner warden can change the QR code.');
    }
    $err = hostel_regenerate_qr($hid);
    flash($err ? 'err' : 'ok', $err ?: 'New QR code created. The old printed code no longer works.');
    redirect('warden/qr.php');
}
$link = hostel_qr_url($h);
$local = in_array(parse_url($link, PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true);
page_header('QR code', $user);
?>
<h1>Your QR code</h1>
<?php if ($local): ?><div class="flash flash-info">The site address is <strong><?= e(parse_url($link, PHP_URL_HOST)) ?></strong>, which only works on this computer. Students cannot open it from their phones. Host the site on a public address and update the site address first (see the README).</div><?php endif; ?>
<div class="card qr-card" id="poster">
  <h2><?= e($h['name']) ?></h2>
  <p>Scan to report a problem</p>
  <div id="qr" data-link="<?= e($link) ?>" aria-label="QR code for <?= e($h['name']) ?>"></div>
  <p class="muted small"><?= e($link) ?></p>
</div>
<div class="row">
  <button class="btn" type="button" id="qr-print">Print poster</button>
  <button class="btn ghost" type="button" id="qr-png">Download PNG</button>
  <?php if ($user['is_owner']): ?>
  <form method="post" data-confirm="Make a new QR code? Printed copies of the old one will stop working."><?= csrf_field() ?><button class="btn ghost" type="submit">Make a new QR code</button></form>
  <?php endif; ?>
</div>
<script src="<?= e(url('assets/js/qrcode.js')) ?>"></script>
<script src="<?= e(url('assets/js/qr.js')) ?>"></script>
<?php page_footer(); ?>
