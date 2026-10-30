<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$hid = (int) $user['hostel_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    if ($action === 'add') {
        $name = post('name');
        $trade = post('trade');
        $phone = sms_normalize_phone(post('phone'));
        if ($name === '' || $trade === '' || mb_strlen($name) > 120 || mb_strlen($trade) > 80) {
            flash('err', 'Enter the name and the trade (plumber, electrician...).');
        } elseif ($phone === null) {
            flash('err', 'Enter a valid mobile number.');
        } else {
            q('INSERT INTO staff (hostel_id, name, trade, phone, note, active) VALUES (?,?,?,?,?,1)', [$hid, $name, $trade, $phone, mb_substr(post('note'), 0, 200) ?: null]);
            flash('ok', 'Added to your service providers.');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE staff SET active = 1 - active WHERE id = ? AND hostel_id = ?', [(int) post('id'), $hid]);
        flash('ok', 'Status changed.');
    } elseif ($action === 'delete') {
        $used = (int) q_val('SELECT COUNT(*) FROM complaints WHERE assigned_staff_id = ? AND hostel_id = ?', [(int) post('id'), $hid]);
        if ($used > 0) {
            flash('err', 'This person is linked to complaints. Hide them instead of deleting.');
        } else {
            q('DELETE FROM staff WHERE id = ? AND hostel_id = ?', [(int) post('id'), $hid]);
            flash('ok', 'Removed.');
        }
    }
    redirect('warden/staff.php');
}
$rows = q_all(
    "SELECT s.*,
        (SELECT COUNT(*) FROM complaints c WHERE c.assigned_staff_id = s.id AND c.status NOT IN ('resolved','closed')) AS open_n,
        (SELECT COUNT(*) FROM complaints c WHERE c.assigned_staff_id = s.id AND c.status IN ('resolved','closed')) AS done_n
     FROM staff s WHERE s.hostel_id = ? ORDER BY s.active DESC, s.trade, s.name", [$hid]
);
page_header('Service providers', $user);
?>
<h1>Nearby service providers</h1>
<p class="muted">Plumbers, electricians and others you call for repairs. They do not log in. Assign a complaint to one so you can track the work, and keep their mobile number here for quick calls.</p>
<div class="cols">
  <section class="card">
    <?php if (!$rows): ?><?= empty_state('No providers yet. Add the first person using the form.') ?>
    <?php else: ?>
    <div class="table-wrap"><table>
      <tr><th>Name</th><th>Trade</th><th>Mobile</th><th>Open</th><th>Done</th><th></th></tr>
      <?php foreach ($rows as $s): ?>
      <tr class="<?= $s['active'] ? '' : 'muted' ?>">
        <td><?= e($s['name']) ?><?= $s['note'] ? '<br><small class="muted">' . e($s['note']) . '</small>' : '' ?></td>
        <td><?= e($s['trade']) ?></td>
        <td><a href="tel:<?= e($s['phone']) ?>"><?= e($s['phone']) ?></a></td>
        <td><?= (int) $s['open_n'] ?></td><td><?= (int) $s['done_n'] ?></td>
        <td class="row">
          <?php foreach (['toggle' => $s['active'] ? 'Hide' : 'Show', 'delete' => 'Delete'] as $a => $l): ?>
          <form method="post" class="inline" <?= $a === 'delete' ? 'data-confirm="Delete this provider?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn small ghost" type="submit"><?= $l ?></button></form>
          <?php endforeach; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
  </section>
  <aside class="card">
    <h2>Add provider</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <label>Name<input name="name" required maxlength="120"></label>
      <label>Trade<input name="trade" required maxlength="80" placeholder="Plumber, Electrician, Carpenter"></label>
      <label>Mobile number<input name="phone" required maxlength="30" inputmode="tel"></label>
      <label>Note (optional)<input name="note" maxlength="200" placeholder="e.g. 5 minutes from gate, evenings only"></label>
      <button class="btn" type="submit">Add</button>
    </form>
  </aside>
</div>
<?php page_footer(); ?>
