<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$a = analytics((int) $user['hostel_id']);
page_header('Analytics', $user);
function bars(array $rows, string $key = 'name'): string
{
    if (!$rows) {
        return empty_state('No data yet.');
    }
    $max = max(array_map(fn($r) => (int) $r['n'], $rows)) ?: 1;
    $h = '<ul class="hbars">';
    foreach ($rows as $r) {
        $h .= '<li><span>' . e($r[$key]) . '</span><div class="bar"><i style="width:' . round(100 * $r['n'] / $max) . '%"></i></div><span class="val">' . (int) $r['n'] . '</span></li>';
    }
    return $h . '</ul>';
}
?>
<h1>Analytics</h1>
<div class="tiles">
  <div class="tile"><b><?= $a['total'] ?></b><span>Complaints</span></div>
  <div class="tile"><b><?= $a['open'] ?></b><span>Open</span></div>
  <div class="tile <?= $a['overdue'] ? 'alert' : '' ?>"><b><?= $a['overdue'] ?></b><span>Overdue</span></div>
  <div class="tile"><b><?= $a['median_hours'] === null ? '-' : e(hours_label($a['median_hours'])) ?></b><span>Median time to resolve</span></div>
  <div class="tile"><b><?= $a['sla_rate'] === null ? '-' : round($a['sla_rate'] * 100) . '%' ?></b><span>Resolved on time</span></div>
  <div class="tile"><b><?= $a['avg_rating'] === null ? '-' : number_format($a['avg_rating'], 1) ?></b><span>Average rating (<?= $a['rating_n'] ?>)</span></div>
</div>
<div class="cols even">
  <div class="card"><h2>By category</h2><?= bars($a['by_category']) ?></div>
  <div class="card"><h2>By block</h2><?= bars($a['by_block']) ?></div>
</div>
<div class="card"><h2>Last six months</h2>
  <ul class="hbars"><?php $max = max($a['months']) ?: 1; foreach ($a['months'] as $m => $n): ?>
    <li><span><?= e($m) ?></span><div class="bar"><i style="width:<?= round(100 * $n / $max) ?>%"></i></div><span class="val"><?= (int) $n ?></span></li><?php endforeach; ?></ul>
</div>
<div class="card"><h2>Service providers</h2>
  <?php if (!$a['staff']): ?><?= empty_state('No providers added.') ?><?php else: ?>
  <div class="table-wrap"><table><tr><th>Name</th><th>Trade</th><th>Open</th><th>Done</th></tr>
  <?php foreach ($a['staff'] as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= e($s['trade']) ?></td><td><?= (int) $s['open_n'] ?></td><td><?= (int) $s['done_n'] ?></td></tr><?php endforeach; ?>
  </table></div><?php endif; ?>
</div>
<?php page_footer(); ?>
