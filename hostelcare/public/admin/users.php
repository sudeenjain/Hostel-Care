<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    if ($action === 'create') {
        $newId = null;
        $errors = create_user([
            'hostel_id' => null, 'login_id' => post('email'), 'name' => post('name'), 'email' => post('email'),
            'phone' => post('phone'), 'role' => 'admin', 'password' => (string) ($_POST['password'] ?? ''),
        ], $newId);
        if (!$errors) {
            flash('ok', 'Administrator account created.');
            redirect('admin/users.php');
        }
    } elseif ($action === 'toggle') {
        $target = (int) post('id');
        if ($target === (int) $user['id']) {
            flash('err', 'You cannot disable your own account.');
        } elseif ((int) q_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1") <= 1 && q_val("SELECT active FROM users WHERE id = ? AND role = 'admin'", [$target]) == 1) {
            flash('err', 'At least one active administrator is required.');
        } else {
            q("UPDATE users SET active = 1 - active WHERE id = ? AND role = 'admin'", [$target]);
            flash('ok', 'Account status changed.');
        }
        redirect('admin/users.php');
    }
}
$admins = q_all("SELECT * FROM users WHERE role = 'admin' ORDER BY name");
page_header('Administrators', $user);
?>
<h1>Platform administrators</h1>
<p class="muted">Administrators approve hostels and receive the approval emails. They cannot read any hostel's complaints.</p>
<div class="cols">
  <section class="card">
    <div class="table-wrap"><table>
      <tr><th>Name</th><th>Email</th><th>Status</th><th></th></tr>
      <?php foreach ($admins as $a): ?>
      <tr class="<?= $a['active'] ? '' : 'muted' ?>"><td><?= e($a['name']) ?></td><td><?= e($a['email']) ?></td><td><?= $a['active'] ? 'Active' : 'Disabled' ?></td>
        <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
          <button class="btn small ghost" type="submit"><?= $a['active'] ? 'Disable' : 'Enable' ?></button></form></td></tr>
      <?php endforeach; ?>
    </table></div>
  </section>
  <aside class="card">
    <h2>Add administrator</h2>
    <?php foreach ($errors as $err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="create">
      <label>Full name<input name="name" required value="<?= e(post('name')) ?>"></label>
      <label>Email<input type="email" name="email" required value="<?= e(post('email')) ?>"></label>
      <label>Mobile (optional)<input name="phone" value="<?= e(post('phone')) ?>"></label>
      <label>Password<input type="password" name="password" required minlength="<?= (int) cfg('rules.min_password') ?>" autocomplete="new-password"></label>
      <button class="btn" type="submit">Create</button>
    </form>
  </aside>
</div>
<?php page_footer(); ?>
