<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');

$id = (int) get('id');
$c = get_complaint($id);
if (!$c) {
    not_found();
}
if (!can_view_complaint($user, $c)) {
    forbidden('This complaint is confidential and visible to wardens only.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $err = null;
    $okMsg = 'Saved.';
    if ($action === 'status') {
        $err = staff_set_status($user, $id, post('to'), post('note'));
        $okMsg = 'Status updated and the student was notified.';
    } elseif ($action === 'assign') {
        $err = assign_staff($user, $id, (int) post('staff_id'), post('note'));
        $okMsg = 'Staff assigned and the student was notified.';
    } elseif ($action === 'note') {
        $err = add_note($user, $id, post('note'), post('internal') === '1');
        $okMsg = 'Note added.';
    } else {
        $err = 'Unknown action.';
    }
    flash($err ? 'err' : 'ok', $err ?: $okMsg);
    redirect('warden/view.php?id=' . $id);
}

$timeline = complaint_timeline($id, true);
$files = complaint_attachments($id);
$next = array_values(array_intersect(['acknowledged', 'in_progress', 'resolved'], status_flow()[$c['status']] ?? []));
$staff = staff_active((int) $user['hostel_id']);
$late = is_overdue($c);
page_header(ticket_no($id), $user);
?>
<div class="row between">
  <div>
    <h1><?= e($c['title']) ?></h1>
    <p class="muted"><?= e(ticket_no($id)) ?> &middot; <?= e($c['category']) ?> &middot; <?= e($c['location']) ?>
      <?= $c['is_confidential'] ? ' <span class="tag">Confidential</span>' : '' ?></p>
  </div>
  <div><?= status_badge($c['status']) ?><?= $late ? ' <span class="tag late">Overdue</span>' : '' ?></div>
</div>

<div class="cols">
  <section>
    <div class="card">
      <h2>Complaint</h2>
      <p class="pre"><?= e($c['description']) ?></p>
      <dl class="facts">
        <dt>Student</dt><dd><?= e($c['student_name']) ?> (<?= e($c['student_login_id']) ?>)</dd>
        <dt>Room</dt><dd>Block <?= e($c['student_block']) ?>, Room <?= e($c['student_room']) ?></dd>
        <?php foreach (form_answers_decode($c['extra_json'] ?? null) as $ans): ?><dt><?= e($ans['label']) ?></dt><dd><?= e($ans['value']) ?></dd><?php endforeach; ?>
        <dt>Contact</dt><dd><?= e($c['student_phone'] ?: 'Not given') ?></dd>
        <dt>Priority</dt><dd><?= priority_tag($c['priority']) ?></dd>
        <dt>Raised</dt><dd><?= e(dt($c['created_at'])) ?></dd>
        <dt>Target date</dt><dd><?= e(dt($c['due_at'])) ?> <small class="muted">(<?= (int) $c['sla_hours'] ?> h for this category)</small></dd>
        <dt>Assigned to</dt><dd><?= $c['staff_name'] ? e($c['staff_name'] . ' (' . $c['staff_trade'] . ')') : 'Nobody yet' ?></dd>
        <?php if ($c['rating']): ?><dt>Rating</dt><dd><?= (int) $c['rating'] ?> / 5<?= $c['feedback'] ? ' &middot; ' . e($c['feedback']) : '' ?></dd><?php endif; ?>
      </dl>
      <?php if ($files): ?>
        <h3>Photos from the student</h3>
        <div class="thumbs">
        <?php foreach ($files as $f): ?>
          <a href="<?= e(url('download.php?id=' . (int) $f['id'])) ?>" target="_blank" rel="noopener">
            <img src="<?= e(url('download.php?id=' . (int) $f['id'])) ?>" alt="<?= e($f['original_name']) ?>">
          </a>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>History</h2>
      <ul class="timeline">
        <?php foreach ($timeline as $t): ?>
          <li class="<?= $t['new_status'] ? 'done' : 'note' ?> <?= $t['is_internal'] ? 'internal' : '' ?>">
            <b><?= $t['new_status'] && $t['new_status'] !== $t['old_status'] ? e(status_label($t['new_status'])) : ($t['new_status'] ? 'Update' : 'Message') ?></b>
            <?php if ($t['is_internal']): ?><span class="tag">Internal</span><?php endif; ?>
            <?php if ($t['note']): ?><p><?= e($t['note']) ?></p><?php endif; ?>
            <small><?= e($t['user_name'] ?? 'System') ?> &middot; <?= e(dt($t['created_at'])) ?></small>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <aside>
    <?php if (is_open_status($c['status'])): ?>
    <div class="card">
      <h2>Update status</h2>
      <?php if ($next): ?>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="status">
        <label>Move to
          <select name="to" required>
            <?php foreach ($next as $s): ?><option value="<?= e($s) ?>"><?= e(status_label($s)) ?></option><?php endforeach; ?>
          </select></label>
        <label>Note to student <small class="muted">(required when resolving)</small>
          <textarea name="note" rows="2" maxlength="2000"></textarea></label>
        <button class="btn" type="submit">Update and notify</button>
      </form>
      <?php else: ?><p class="muted">No further manual status changes from <?= e(status_label($c['status'])) ?>.</p><?php endif; ?>
    </div>

    <div class="card">
      <h2>Assign a service provider</h2>
      <?php if (!$staff): ?>
        <p class="muted">No service providers added yet. <a href="<?= e(url('warden/staff.php')) ?>">Add one</a> first.</p>
      <?php else: ?>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="assign">
        <label>Staff member
          <select name="staff_id" required>
            <option value="">Choose</option>
            <?php foreach ($staff as $s): ?>
              <option value="<?= (int) $s['id'] ?>" <?= (int) $c['assigned_staff_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name'] . ' - ' . $s['trade'] . ' - ' . $s['phone']) ?></option>
            <?php endforeach; ?>
          </select></label>
        <label>Instruction (optional)<input name="note" maxlength="300"></label>
        <button class="btn ghost" type="submit">Assign</button>
      </form>
      <?php endif; ?>
    </div>
    <?php elseif ($c['status'] === 'resolved'): ?>
      <div class="card"><p class="muted">Resolved. Waiting for the student to confirm. It closes automatically <?= (int) cfg('rules.auto_close_days') ?> days after resolution.</p></div>
    <?php endif; ?>

    <div class="card">
      <h2>Add a note</h2>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="note">
        <textarea name="note" rows="3" required minlength="2" maxlength="2000"></textarea>
        <label class="check"><input type="checkbox" name="internal" value="1"><span>Internal only (the student will not see it)</span></label>
        <button class="btn ghost" type="submit">Save note</button>
      </form>
    </div>
  </aside>
</div>
<?php page_footer(); ?>
