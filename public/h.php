<?php
require __DIR__ . '/../includes/bootstrap.php';

/* Landing page for a scanned QR code. It reveals only this one hostel's name, and nothing about any other hostel. */
$token = get('c') ?: post('c');
$h = hostel_by_qr($token);
if (!$h) {
    http_response_code(404);
    page_header('Link not working', null);
    echo '<div class="card narrow"><h1>This link is not working</h1><p class="muted">The QR code may be old or the hostel is not accepting complaints right now. Ask your warden for the current QR code.</p></div>';
    page_footer();
    exit;
}
$u = current_user();
if ($u && $u['role'] === 'student' && (int) $u['hostel_id'] === (int) $h['id']) {
    redirect('student/new.php');
}
$error = null;
if (!$u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $error = login_attempt(post('identifier'), (string) ($_POST['password'] ?? ''), (int) $h['id']);
    if ($error === null) {
        $nu = current_user();
        redirect($nu && $nu['role'] === 'student' ? 'student/new.php' : home_for($nu));
    }
}
page_header($h['name'], null);
?>
<div class="card narrow">
  <h1><?= e($h['name']) ?></h1>
  <p class="muted">Report a problem to your hostel warden.</p>
  <?php if ($u): ?>
    <div class="flash flash-info">You are signed in as <?= e($u['name']) ?>, who does not belong to this hostel. Sign out to continue.</div>
    <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Sign out</button></form>
  <?php else: ?>
    <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="c" value="<?= e($token) ?>">
      <label>Hostel ID<input name="identifier" required autofocus autocomplete="username" value="<?= e(post('identifier')) ?>"></label>
      <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn" type="submit">Sign in</button>
    </form>
    <?php if ((int) $h['registration_open']): ?>
      <p class="muted">First time here? <a href="<?= e(url('register.php?c=' . $token)) ?>">Create your account</a></p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
