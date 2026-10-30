<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
auto_close_resolved();

$hid = (int) $user['hostel_id'];
$page = max(1, (int) get('page', '1'));
$filter = [
    'status' => get('status', 'open'), 'category_id' => get('category_id'), 'priority' => get('priority'),
    'block' => get('block'), 'overdue' => get('overdue'), 'q' => get('q'),
];
[$rows, $total] = list_complaints($user, $filter, $page);

$openN = (int) q_val("SELECT COUNT(*) FROM complaints WHERE hostel_id = ? AND status NOT IN ('resolved','closed')", [$hid]);
$overdueN = (int) q_val("SELECT COUNT(*) FROM complaints WHERE hostel_id = ? AND status NOT IN ('resolved','closed') AND due_at < ?", [$hid, now()]);
$unassignedN = (int) q_val("SELECT COUNT(*) FROM complaints WHERE hostel_id = ? AND status NOT IN ('resolved','closed') AND assigned_staff_id IS NULL", [$hid]);
$awaitingN = (int) q_val("SELECT COUNT(*) FROM complaints WHERE hostel_id = ? AND status = 'resolved'", [$hid]);
$cats = q_all('SELECT id, name FROM categories WHERE hostel_id = ? ORDER BY name', [$hid]);
$blocks = q_all("SELECT DISTINCT block FROM users WHERE hostel_id = ? AND block IS NOT NULL AND role = 'student' ORDER BY block", [$hid]);
$query = array_filter($filter, fn($v) => $v !== '');

page_header('Complaint queue', $user);
?>
<h1>Complaint queue</h1>
<p class="muted">Overdue complaints are listed first, then by priority and age.</p>

<div class="tiles">
  <a class="tile" href="?status=open"><b><?= $openN ?></b><span>Open</span></a>
  <a class="tile <?= $overdueN ? 'alert' : '' ?>" href="?status=open&overdue=1"><b><?= $overdueN ?></b><span>Past target date</span></a>
  <div class="tile"><b><?= $unassignedN ?></b><span>Not assigned</span></div>
  <a class="tile" href="?status=resolved"><b><?= $awaitingN ?></b><span>Awaiting student confirmation</span></a>
</div>

<form method="get" class="filters">
  <label>Search<input name="q" value="<?= e($filter['q']) ?>" placeholder="Ticket or title"></label>
  <label>Status
    <select name="status">
      <option value="">All</option>
      <option value="open" <?= $filter['status'] === 'open' ? 'selected' : '' ?>>Open only</option>
      <?php foreach (status_labels() as $k => $v): ?>
        <option value="<?= e($k) ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select></label>
  <label>Category
    <select name="category_id"><option value="">All</option>
      <?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (string) $filter['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select></label>
  <label>Priority
    <select name="priority"><option value="">All</option>
      <?php foreach (priority_labels() as $k => $v): ?><option value="<?= e($k) ?>" <?= $filter['priority'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select></label>
  <label>Block
    <select name="block"><option value="">All</option>
      <?php foreach ($blocks as $b): ?><option value="<?= e($b['block']) ?>" <?= $filter['block'] === $b['block'] ? 'selected' : '' ?>><?= e($b['block']) ?></option><?php endforeach; ?>
    </select></label>
  <label class="check"><input type="checkbox" name="overdue" value="1" <?= $filter['overdue'] ? 'checked' : '' ?>><span>Past target only</span></label>
  <button class="btn small" type="submit">Apply</button>
  <a class="btn small ghost" href="?status=">Clear</a>
</form>

<div class="card">
<?php if (!$rows): ?>
  <?= empty_state($openN === 0 && (int) q_val('SELECT COUNT(*) FROM complaints WHERE hostel_id = ?', [$hid]) === 0
      ? 'No complaints have been raised yet. They will appear here as students submit them.'
      : 'No complaints match these filters.') ?>
<?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Ticket</th><th>Student / room</th><th>Issue</th><th>Priority</th><th>Status</th><th>Handler</th><th>Target</th><th></th></tr>
    <?php foreach ($rows as $r): $late = is_overdue($r); ?>
    <tr class="<?= $late ? 'late-row' : '' ?>">
      <td><?= e(ticket_no((int) $r['id'])) ?></td>
      <td><?= e($r['student_name']) ?><br><small class="muted"><?= $r['block'] !== 'Hidden' ? e($r['block'] . ' / ' . $r['room']) : '' ?></small></td>
      <td><?= e($r['title']) ?><br><small class="muted"><?= e($r['category']) ?></small><?= $r['is_confidential'] ? ' <span class="tag">Confidential</span>' : '' ?></td>
      <td><?= priority_tag($r['priority']) ?></td>
      <td><?= status_badge($r['status']) ?></td>
      <td><?= $r['staff_name'] ? e($r['staff_name']) : '<span class="muted">-</span>' ?></td>
      <td><?= e(dt($r['due_at'])) ?><?= $late ? ' <span class="tag late">Overdue</span>' : '' ?></td>
      <td><a class="btn small ghost" href="<?= e(url('warden/view.php?id=' . (int) $r['id'])) ?>">Open</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <p class="muted small"><?= $total ?> complaint<?= $total === 1 ? '' : 's' ?> found</p>
  <?= pager($total, $page, $query) ?>
<?php endif; ?>
</div>
<?php page_footer(); ?>
