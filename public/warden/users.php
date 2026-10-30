<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$hid = (int) $user['hostel_id'];
$errors = [];
$newPass = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $target = (int) post('id');
    $row = $target ? q_one('SELECT * FROM users WHERE id = ? AND hostel_id = ?', [$target, $hid]) : null;
    if ($action === 'create_warden') {
        if (!$user['is_owner']) {
            forbidden('Only the owner warden can add other wardens.');
        }
        $nid = null;
        $errors = create_user(['hostel_id' => $hid, 'login_id' => post('email'), 'name' => post('name'), 'email' => post('email'), 'phone' => post('phone'), 'role' => 'warden', 'is_owner' => 0, 'password' => (string) ($_POST['password'] ?? '')], $nid);
        if (!$errors) {
            flash('ok', 'Assistant warden added. They sign in with their email.');
            redirect('warden/users.php');
        }
    } elseif ($row && $action === 'toggle') {
        if ((int) $row['id'] === (int) $user['id'] || $row['is_owner']) {
            flash('err', 'You cannot disable the owner or yourself.');
        } elseif ($row['role'] === 'warden' && !$user['is_owner']) {
            flash('err', 'Only the owner warden can change wardens.');
        } else {
            q('UPDATE users SET active = 1 - active WHERE id = ? AND hostel_id = ?', [$target, $hid]);
            flash('ok', 'Account status changed.');
        }
        redirect('warden/users.php');
    } elseif ($row && $action === 'reset' && $row['role'] === 'student') {
        $newPass = substr(bin2hex(random_bytes(6)), 0, 10);
        q('UPDATE users SET password_hash = ? WHERE id = ? AND hostel_id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $target, $hid]);
        $resetName = $row['name'];
    }
}
$search = trim(get('q'));
$sql = 'SELECT * FROM users WHERE hostel_id = ?';
$p = [$hid];
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR login_id LIKE ? OR email LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
    array_push($p, $like, $like, $like);
}
$people = q_all($sql . " ORDER BY role = 'warden' DESC, name LIMIT 300", $p);
page_header('People', $user);
?>
<h1>People in your hostel</h1>
<?php if ($newPass): ?>
  <div class="flash flash-ok">Temporary password for <?= e($resetName) ?>: <strong id="temp-pass"><?= e($newPass) ?></strong>. Tell them in person and ask them to change it from Profile. It is not shown again.</div>
<?php endif; ?>
<?php foreach ($errors as $err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endforeach; ?>
<div class="cols">
  <section class="card">
    <form method="get" class="row"><input name="q" value="<?= e($search) ?>" placeholder="Search name, hostel ID or email"><button class="btn ghost" type="submit">Search</button></form>
    <?php if (!$people): ?><?= empty_state('Nobody here yet. Students appear after they scan your QR code and register.') ?>
    <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Name</th><th>Role</th><th>ID / Email</th><th>Room</th><th></th></tr>
      <?php foreach ($people as $r): ?>
      <tr class="<?= $r['active'] ? '' : 'muted' ?>">
        <td><?= e($r['name']) ?></td><td><?= $r['role'] === 'warden' ? ($r['is_owner'] ? 'Owner warden' : 'Warden') : 'Student' ?></td>
        <td><?= e($r['role'] === 'student' ? $r['login_id'] : $r['email']) ?></td>
        <td><?= e(trim(($r['block'] ?? '') . ' ' . ($r['room'] ?? ''))) ?></td>
        <td class="row">
          <?php if (!$r['is_owner'] && (int) $r['id'] !== (int) $user['id']): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn small ghost" type="submit"><?= $r['active'] ? 'Disable' : 'Enable' ?></button></form>
          <?php endif; ?>
          <?php if ($r['role'] === 'student'): ?>
          <form method="post" class="inline" data-confirm="Create a new temporary password for this student?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn small ghost" type="submit">Reset password</button></form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </section>
  <?php if ($user['is_owner']): ?>
  <aside class="card">
    <h2>Add assistant warden</h2>
    <p class="muted">Assistant wardens handle complaints. They cannot read confidential complaints.</p>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="create_warden">
      <label>Full name<input name="name" required value="<?= e(post('name')) ?>"></label>
      <label>Email<input type="email" name="email" required value="<?= e(post('email')) ?>"></label>
      <label>Mobile (optional)<input name="phone" value="<?= e(post('phone')) ?>"></label>
      <label>Password<input type="password" name="password" required minlength="<?= (int) cfg('rules.min_password') ?>" autocomplete="new-password"></label>
      <button class="btn" type="submit">Add</button>
    </form>
  </aside>
  <?php endif; ?>
</div>
<?php page_footer(); ?>
