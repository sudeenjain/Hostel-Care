<?php
require __DIR__ . '/../includes/bootstrap.php';

$already = false;
try {
    $already = db_installed() && (int) q_val("SELECT COUNT(*) FROM users WHERE role = 'admin'") > 0;
} catch (Throwable $e) {
    $already = false;
}
if ($already) {
    flash('info', 'HostelCare is already installed.');
    redirect('login.php');
}

$errors = [];
// Check PHP extensions first, so a missing driver is explained instead of failing mid-install.
$missing = [];
$driverExt = cfg('db.driver') === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite';
if (!extension_loaded('pdo') || !extension_loaded($driverExt)) {
    $missing[] = $driverExt . ' (needed for the ' . cfg('db.driver') . ' database)';
}
foreach (['fileinfo' => 'to check uploaded photos', 'mbstring' => 'for text handling', 'ctype' => 'to check phone numbers', 'openssl' => 'for secure email'] as $ext => $why) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext . ' (needed ' . $why . ')';
    }
}
$old = ['name' => '', 'email' => '', 'site' => detect_site_address()];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$missing) {
    csrf_verify();
    foreach (array_keys($old) as $k) {
        $old[$k] = post($k);
    }
    $site = rtrim($old['site'], '/');
    if (!preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?$#', $site)) {
        $errors[] = 'Enter the site address like http://localhost:8000 or https://care.example.edu (no path at the end).';
    }
    if (!$errors) {
        try {
            db_install_schema();
            $newId = null;
            $errors = create_user([
                'hostel_id' => null, 'login_id' => $old['email'], 'name' => $old['name'], 'email' => $old['email'],
                'password' => (string) ($_POST['password'] ?? ''), 'role' => 'admin',
            ], $newId);
            if (!$errors) {
                set_setting('public_url', $site);
                set_setting('installed_at', now());
                $_SESSION['user_id'] = $newId;
                flash('ok', 'Installation complete. You are signed in as the platform administrator.');
                redirect('admin/dashboard.php');
            }
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$writable = is_writable(HC_ROOT . '/storage') && is_writable(HC_ROOT . '/storage/uploads');
page_header('Install', null);
?>
<div class="card narrow">
  <h1>Set up <?= e(cfg('app.name')) ?></h1>
  <p class="muted">This starts with an empty system. Nothing is pre-filled. You create the platform administrator, who approves hostels. Each hostel then sets up its own categories, complaint form and QR code.</p>
  <p class="muted">Database driver: <strong><?= e(cfg('db.driver')) ?></strong>
    &middot; Storage folder writable: <strong><?= $writable ? 'yes' : 'NO - fix permissions on storage/' ?></strong></p>
  <?php if ($missing): ?>
    <div class="flash flash-err">
      <strong>Your PHP is missing a required extension:</strong>
      <ul style="margin:.4rem 0 .4rem 1.2rem"><?php foreach ($missing as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
      Open your <code>php.ini</code> (run <code>php --ini</code> to find it), remove the <code>;</code> in front of the matching
      <code>extension=</code> line, save, restart the PHP server, and reload this page. Or switch database with
      <code>HC_DB_DRIVER=mysql</code> (see the README).
    </div>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endforeach; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <h2>Platform administrator</h2>
    <label>Full name<input name="name" required value="<?= e($old['name']) ?>"></label>
    <label>Email (you sign in with this, and hostel requests are sent here)<input type="email" name="email" required value="<?= e($old['email']) ?>"></label>
    <label>Password (min <?= (int) cfg('rules.min_password') ?> characters)<input type="password" name="password" required minlength="<?= (int) cfg('rules.min_password') ?>" autocomplete="new-password"></label>
    <h2>Site address</h2>
    <label>Address people will use to reach this site
      <input name="site" required value="<?= e($old['site']) ?>"></label>
    <p class="muted small">This address goes into emails and QR codes. Use the real public address once the site is online. If you test on your own computer, only your computer can open <code>localhost</code> links; for other people on the same Wi-Fi use your computer's network address, for example <code>http://192.168.1.20:8000</code>.</p>
    <button class="btn" type="submit" <?= $missing ? 'disabled' : '' ?>>Install</button>
  </form>
</div>
<?php page_footer(); ?>
