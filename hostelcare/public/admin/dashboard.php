<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('admin');
hostel_cleanup();

$filter = get('status');
$where = isset(hostel_status_label_map()[$filter]) ? 'WHERE h.status = ?' : '';
$rows = q_all(
    "SELECT h.* FROM hostels h $where ORDER BY CASE h.status WHEN 'pending' THEN 0 ELSE 1 END, h.created_at DESC",
    $where ? [$filter] : []
);
$totals = [];
foreach (q_all('SELECT status, COUNT(*) AS n FROM hostels GROUP BY status') as $r) {
    $totals[$r['status']] = (int) $r['n'];
}
page_header('Hostels', $user);
?>
<h1>Hostels on the platform</h1>
<p class="muted">You see each hostel's status and totals only. Complaints belong to the hostels and are not visible here.</p>

<div class="tiles">
  <a class="tile <?= ($totals['pending'] ?? 0) ? 'alert' : '' ?>" href="<?= e(url('admin/requests.php')) ?>"><b><?= $totals['pending'] ?? 0 ?></b><span>Waiting for your decision</span></a>
  <a class="tile" href="?status=active"><b><?= $totals['active'] ?? 0 ?></b><span>Active hostels</span></a>
  <a class="tile" href="?status=approved"><b><?= $totals['approved'] ?? 0 ?></b><span>Approved, setting up</span></a>
  <a class="tile" href="?status=suspended"><b><?= $totals['suspended'] ?? 0 ?></b><span>Suspended</span></a>
</div>

<div class="card">
<?php if (!$rows): ?>
  <?= empty_state('No hostel has asked to join yet. Share the "Register my hostel" link with wardens.') ?>
<?php else: ?>
  <div class="table-wrap"><table>
    <tr><th>Hostel</th><th>City</th><th>Warden</th><th>Status</th><th>Students</th><th>Complaints</th><th>Requested</th><th></th></tr>
    <?php foreach ($rows as $h): $c = platform_hostel_counts((int) $h['id']); ?>
    <tr>
      <td><?= e($h['name']) ?><br><small class="muted"><?= e(hostel_types()[$h['type']] ?? '') ?></small></td>
      <td><?= e($h['city']) ?></td>
      <td><?= e($h['owner_name']) ?><br><small class="muted"><?= e($h['owner_email']) ?></small></td>
      <td><?= hostel_badge($h['status']) ?></td>
      <td><?= $c['students'] ?></td>
      <td><?= $c['complaints'] ?> <small class="muted">(<?= $c['open'] ?> open)</small></td>
      <td><?= e(ago($h['created_at'])) ?></td>
      <td><a class="btn small ghost" href="<?= e(url('admin/hostel.php?id=' . (int) $h['id'])) ?>">Details</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
<?php endif; ?>
</div>
<?php page_footer(); ?>
