<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('student');
auto_close_resolved();

$page = max(1, (int) get('page', '1'));
$filter = ['status' => get('status')];
[$rows, $total] = list_complaints($user, $filter, $page);

$counts = ['all' => 0, 'open' => 0, 'resolved' => 0];
foreach (q_all('SELECT status, COUNT(*) AS n FROM complaints WHERE student_id = ? GROUP BY status', [$user['id']]) as $r) {
  $counts['all'] += (int) $r['n'];
  if (is_open_status($r['status'])) {
    $counts['open'] += (int) $r['n'];
  } else {
    $counts['resolved'] += (int) $r['n'];
  }
}
page_header('My complaints', $user);
?>
<style>
  .sd {
    --blue: #2f80ed;
    --blue-d: #1b64c9;
    --sky: #eaf4ff;
    --sky2: #d6eaff;
    --ink: #0f2742;
    --muted: #5b6f87;
    --line: #d9e6f5;
    --warn: #d97706;
    --ok: #1f9d57;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    max-width: 1100px;
    margin: 0 auto;
  }

  .sd * {
    box-sizing: border-box;
  }

  /* header banner */
  .sd-head {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
    justify-content: space-between;
    padding: 30px 32px;
    border-radius: 26px;
    background: linear-gradient(135deg, #d6eaff, #eaf4ff 60%, #fff);
    border: 1px solid var(--line);
  }

  .sd-head::before {
    content: "";
    position: absolute;
    width: 380px;
    height: 380px;
    right: -100px;
    top: -160px;
    border-radius: 50%;
    background: #7fb8ff;
    opacity: .35;
    filter: blur(90px);
  }

  .sd-head>* {
    position: relative;
  }

  .sd-head h1 {
    margin: 0 0 8px;
    font-size: clamp(1.6rem, 3.2vw, 2.2rem);
    font-weight: 800;
    letter-spacing: -.02em;
  }

  .sd-who {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    color: var(--muted);
    font-size: .93rem;
  }

  .sd-who span {
    padding: 5px 12px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--sky2);
    color: var(--blue-d);
    font-weight: 600;
    font-size: .8rem;
  }

  .sd-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 26px;
    border-radius: 14px;
    border: 1.5px solid transparent;
    font-size: .97rem;
    font-weight: 700;
    text-decoration: none;
    color: #fff;
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
    transition: transform .2s, background .2s;
  }

  .sd-btn:hover {
    transform: translateY(-2px);
  }

  .sd-btn.small {
    padding: 8px 16px;
    font-size: .84rem;
    border-radius: 10px;
    box-shadow: none;
  }

  .sd-btn.light {
    color: var(--blue-d);
    background: #fff;
    border-color: var(--sky2);
    box-shadow: none;
  }

  .sd-btn.light:hover {
    background: var(--sky);
  }

  /* stat tiles */
  .sd-tiles {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin: 22px 0;
  }

  .sd-tile {
    display: block;
    padding: 22px 24px;
    border-radius: 20px;
    background: #fff;
    border: 1.5px solid var(--line);
    text-decoration: none;
    color: inherit;
    box-shadow: 0 8px 24px rgba(47, 128, 237, .08);
    transition: transform .25s, border-color .25s, box-shadow .25s;
  }

  a.sd-tile:hover {
    transform: translateY(-4px);
    border-color: var(--blue);
    box-shadow: 0 16px 36px rgba(47, 128, 237, .16);
  }

  .sd-tile.on {
    border-color: var(--blue);
    background: var(--sky);
  }

  .sd-tile b {
    display: block;
    font-size: 2.2rem;
    line-height: 1;
    font-weight: 800;
    color: var(--blue);
    margin-bottom: 8px;
  }

  .sd-tile.open b {
    color: var(--warn);
  }

  .sd-tile.res b {
    color: var(--ok);
  }

  .sd-tile span {
    color: var(--muted);
    font-size: .9rem;
    font-weight: 500;
  }

  /* filters */
  .sd-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
  }

  .sd-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .sd-chip {
    padding: 8px 16px;
    border-radius: 999px;
    background: #fff;
    border: 1.5px solid var(--line);
    color: var(--muted);
    font-size: .85rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
  }

  .sd-chip:hover {
    border-color: var(--blue);
    color: var(--blue-d);
  }

  .sd-chip.on {
    background: var(--blue);
    border-color: var(--blue);
    color: #fff;
  }

  .sd-bar form {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
  }

  .sd-bar label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    font-size: .85rem;
    font-weight: 600;
    color: var(--muted);
  }

  .sd-bar select {
    padding: 9px 36px 9px 14px;
    font: inherit;
    font-size: .88rem;
    color: var(--ink);
    background-color: #fff;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    outline: 0;
    width: auto;
  }

  .sd-bar select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .15);
  }

  /* list card + table */
  .sd-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 22px;
    box-shadow: 0 12px 36px rgba(47, 128, 237, .10);
    overflow: hidden;
    padding: 0;
  }

  .sd-card>.sd-empty,
  .sd-card>div:not(.sd-wrap) {
    padding: 30px;
  }

  .sd-wrap {
    overflow-x: auto;
  }

  .sd table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
  }

  .sd th {
    padding: 14px 18px;
    text-align: left;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--blue-d);
    background: #f4f9ff;
    border-bottom: 1px solid var(--line);
    white-space: nowrap;
  }

  .sd td {
    padding: 16px 18px;
    border-bottom: 1px solid #edf3fa;
    font-size: .92rem;
    vertical-align: middle;
  }

  .sd tr:last-child td {
    border-bottom: 0;
  }

  .sd tbody tr {
    transition: background .15s;
  }

  .sd tbody tr:hover {
    background: #f7fbff;
  }

  .sd tr.late td:first-child {
    box-shadow: inset 4px 0 0 var(--warn);
  }

  .sd .tkt {
    font-weight: 700;
    color: var(--blue-d);
    white-space: nowrap;
  }

  .sd .ttl {
    font-weight: 600;
  }

  .sd .tag {
    display: inline-block;
    margin-left: 6px;
    padding: 3px 9px;
    border-radius: 999px;
    background: var(--sky);
    color: var(--blue-d);
    font-size: .72rem;
    font-weight: 700;
  }

  .sd .tag.late {
    background: #fff4e0;
    color: var(--warn);
  }

  .sd .when {
    color: var(--muted);
    white-space: nowrap;
  }

  .sd-pager {
    padding: 16px 18px;
    border-top: 1px solid var(--line);
    text-align: center;
  }

  @media (max-width:820px) {
    .sd-tiles {
      grid-template-columns: 1fr;
    }

    .sd table,
    .sd tbody,
    .sd tr,
    .sd td {
      display: block;
      width: 100%;
    }

    .sd thead {
      display: none;
    }

    .sd tbody tr {
      padding: 14px 16px;
      border-bottom: 1px solid var(--line);
    }

    .sd td {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      padding: 7px 0;
      border: 0;
      text-align: right;
    }

    .sd td::before {
      content: attr(data-label);
      color: var(--muted);
      font-size: .74rem;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      text-align: left;
    }

    .sd td:last-child {
      justify-content: flex-end;
      padding-top: 10px;
    }

    .sd tr.late td:first-child {
      box-shadow: none;
    }

    .sd tr.late {
      box-shadow: inset 4px 0 0 var(--warn);
    }
  }

  @media (max-width:520px) {
    .sd-head {
      padding: 22px 18px;
    }

    .sd-btn {
      width: 100%;
    }
  }
</style>

<div class="sd">

  <div class="sd-head">
    <div>
      <h1>My complaints</h1>
      <div class="sd-who">
        <?= e($user['name']) ?>
        <span>Block <?= e($user['block']) ?></span>
        <span>Room <?= e($user['room']) ?></span>
      </div>
    </div>
    <a class="sd-btn" href="<?= e(url('student/new.php')) ?>">+ New complaint</a>
  </div>

  <div class="sd-tiles">
    <a class="sd-tile <?= $filter['status'] === '' || $filter['status'] === null ? 'on' : '' ?>" href="?"><b><?= $counts['all'] ?></b><span>Total raised</span></a>
    <a class="sd-tile open <?= $filter['status'] === 'open' ? 'on' : '' ?>" href="?status=open"><b><?= $counts['open'] ?></b><span>Still open</span></a>
    <div class="sd-tile res"><b><?= $counts['resolved'] ?></b><span>Resolved or closed</span></div>
  </div>

  <div class="sd-bar">
    <div class="sd-chips">
      <a class="sd-chip <?= !$filter['status'] ? 'on' : '' ?>" href="?">All</a>
      <a class="sd-chip <?= $filter['status'] === 'open' ? 'on' : '' ?>" href="?status=open">Open only</a>
    </div>
    <form method="get">
      <label>Status
        <select name="status" onchange="this.form.submit()">
          <option value="">All</option>
          <option value="open" <?= $filter['status'] === 'open' ? 'selected' : '' ?>>Open only</option>
          <?php foreach (status_labels() as $k => $v): ?>
            <option value="<?= e($k) ?>" <?= $filter['status'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </div>

  <div class="sd-card">
    <?php if (!$rows): ?>
      <div class="sd-empty">
        <?= empty_state($counts['all'] === 0
          ? 'You have not raised any complaint yet. Use "New complaint" when something needs attention.'
          : 'No complaints match this filter.') ?>
      </div>
    <?php else: ?>
      <div class="sd-wrap">
        <table>
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Issue</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Raised</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr class="<?= is_overdue($r) ? 'late' : '' ?>">
                <td class="tkt" data-label="Ticket"><?= e(ticket_no((int) $r['id'])) ?></td>
                <td data-label="Issue"><span class="ttl"><?= e($r['title']) ?></span><?= $r['is_confidential'] ? ' <span class="tag">Confidential</span>' : '' ?></td>
                <td data-label="Category"><?= e($r['category']) ?></td>
                <td data-label="Priority"><?= priority_tag($r['priority']) ?></td>
                <td data-label="Status"><?= status_badge($r['status']) ?><?= is_overdue($r) ? ' <span class="tag late">Past target</span>' : '' ?></td>
                <td class="when" data-label="Raised"><?= e(ago($r['created_at'])) ?></td>
                <td><a class="sd-btn small light" href="<?= e(url('student/view.php?id=' . (int) $r['id'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="sd-pager"><?= pager($total, $page, $filter) ?></div>
    <?php endif; ?>
  </div>

</div>
<?php page_footer(); ?>