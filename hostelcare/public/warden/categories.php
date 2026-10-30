<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$hid = (int) $user['hostel_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    if ($action === 'add' || $action === 'edit') {
        $name = post('name');
        $prio = post('default_priority');
        $sla = (int) post('sla_hours');
        $id = (int) post('id');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            flash('err', 'Enter a category name (2 to 60 characters).');
        } elseif (!in_array($prio, ['low', 'medium', 'high'], true) || $sla < 1 || $sla > 720) {
            flash('err', 'Choose a priority and a deadline between 1 and 720 hours.');
        } elseif (q_val('SELECT 1 FROM categories WHERE hostel_id = ? AND LOWER(name) = LOWER(?) AND id <> ?', [$hid, $name, $id]) !== null) {
            flash('err', 'You already have a category with that name.');
        } elseif ($action === 'add') {
            q('INSERT INTO categories (hostel_id, name, default_priority, sla_hours, active) VALUES (?,?,?,?,1)', [$hid, $name, $prio, $sla]);
            flash('ok', 'Category added.');
        } else {
            q('UPDATE categories SET name = ?, default_priority = ?, sla_hours = ? WHERE id = ? AND hostel_id = ?', [$name, $prio, $sla, $id, $hid]);
            flash('ok', 'Category saved.');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE categories SET active = 1 - active WHERE id = ? AND hostel_id = ?', [(int) post('id'), $hid]);
        flash('ok', 'Category status changed.');
    }
    redirect('warden/categories.php');
}
$rows = q_all('SELECT c.*, (SELECT COUNT(*) FROM complaints x WHERE x.category_id = c.id) AS used FROM categories c WHERE c.hostel_id = ? ORDER BY c.active DESC, c.name', [$hid]);
page_header('Categories', $user);
?>
<h1>Complaint categories</h1>
<p class="muted">The type of problem a student picks. The deadline (hours) is how long you promise to take; complaints become overdue after it.</p>
<div class="cols">
  <section class="card">
    <?php if (!$rows): ?><?= empty_state('No categories yet. Add the first one, for example Plumbing, Electrical, Cleaning.') ?>
    <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Name</th><th>Priority</th><th>Deadline</th><th>Used</th><th></th></tr>
      <?php foreach ($rows as $c): ?>
      <tr class="<?= $c['active'] ? '' : 'muted' ?>">
        <form method="post" id="cat<?= (int) $c['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"></form>
        <td><input form="cat<?= (int) $c['id'] ?>" name="name" value="<?= e($c['name']) ?>" maxlength="60" required></td>
        <td><select form="cat<?= (int) $c['id'] ?>" name="default_priority"><?php foreach (priority_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $c['default_priority'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
        <td><input form="cat<?= (int) $c['id'] ?>" type="number" name="sla_hours" min="1" max="720" value="<?= (int) $c['sla_hours'] ?>" style="width:5rem"> h</td>
        <td><?= (int) $c['used'] ?></td>
        <td class="row">
          <button form="cat<?= (int) $c['id'] ?>" class="btn small" type="submit">Save</button>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn small ghost" type="submit"><?= $c['active'] ? 'Hide' : 'Show' ?></button></form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </section>
  <aside class="card">
    <h2>Add category</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>Name<input name="name" required maxlength="60" placeholder="e.g. Plumbing"></label>
      <label>Default priority<select name="default_priority"><?php foreach (priority_labels() as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === 'medium' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label>Deadline in hours<input type="number" name="sla_hours" min="1" max="720" value="48" required></label>
      <button class="btn" type="submit">Add</button>
    </form>
  </aside>
</div>
<?php page_footer(); ?>
