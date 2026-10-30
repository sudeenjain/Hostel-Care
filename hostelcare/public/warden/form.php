<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$hid = (int) $user['hostel_id'];
$edit = null;
if (($eid = (int) get('edit')) > 0) {
    $edit = q_one('SELECT * FROM hostel_fields WHERE id = ? AND hostel_id = ?', [$eid, $hid]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = post('action');
    $id = (int) post('id');
    if ($action === 'save') {
        $err = form_field_save($hid, ['label' => post('label'), 'field_type' => post('field_type'), 'options' => (string) ($_POST['options'] ?? ''), 'is_required' => post('is_required') === '1'], $id ?: null);
        if ($err) {
            flash('err', $err);
            redirect('warden/form.php' . ($id ? '?edit=' . $id : ''));
        }
        flash('ok', $id ? 'Question saved.' : 'Question added.');
    } elseif ($action === 'toggle') {
        form_field_toggle($hid, $id);
    } elseif ($action === 'delete') {
        form_field_delete($hid, $id);
        flash('ok', 'Question deleted. Past answers stay on old complaints.');
    } elseif ($action === 'up' || $action === 'down') {
        form_field_move($hid, $id, $action);
    }
    redirect('warden/form.php');
}
$fields = form_fields($hid, false);
page_header('Complaint form', $user);
?>
<h1>Complaint form</h1>
<p class="muted">Students always give a category, a title, a description, the block and the room or place. Add your own questions below, as dropdowns or text boxes.</p>
<div class="cols">
  <section class="card">
    <?php if (!$fields): ?><?= empty_state('No extra questions. The standard form will be used.') ?>
    <?php else: ?>
    <ul class="notes">
    <?php foreach ($fields as $f): ?>
      <li class="<?= $f['active'] ? '' : 'muted' ?>">
        <span><strong><?= e($f['label']) ?></strong> <?= $f['is_required'] ? '<span class="tag">Required</span>' : '' ?><br>
          <small class="muted"><?= e(field_types()[$f['field_type']]) ?><?= $f['field_type'] === 'dropdown' ? ': ' . e(implode(', ', field_options($f))) : '' ?></small></span>
        <span class="row">
          <?php foreach (['up' => '&uarr;', 'down' => '&darr;', 'toggle' => $f['active'] ? 'Hide' : 'Show', 'delete' => 'Delete'] as $a => $label): ?>
          <form method="post" class="inline" <?= $a === 'delete' ? 'data-confirm="Delete this question?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="btn small ghost" type="submit"><?= $label ?></button></form>
          <?php endforeach; ?>
          <a class="btn small ghost" href="?edit=<?= (int) $f['id'] ?>">Edit</a>
        </span>
      </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
  <aside class="card">
    <h2><?= $edit ? 'Edit question' : 'Add a question' ?></h2>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <label>Question<input name="label" required maxlength="80" value="<?= e($edit['label'] ?? '') ?>" placeholder="e.g. Is the problem inside the room?"></label>
      <label>Answer type<select name="field_type" id="ftype"><?php foreach (field_types() as $k => $l): ?><option value="<?= e($k) ?>" <?= ($edit['field_type'] ?? 'dropdown') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <label>Dropdown options (one per line, at least two)<textarea name="options" rows="5"><?= e($edit['options'] ?? '') ?></textarea></label>
      <label class="check"><input type="checkbox" name="is_required" value="1" <?= !empty($edit['is_required']) ? 'checked' : '' ?>> Students must answer</label>
      <button class="btn" type="submit"><?= $edit ? 'Save question' : 'Add question' ?></button>
      <?php if ($edit): ?><a href="<?= e(url('warden/form.php')) ?>">Cancel</a><?php endif; ?>
    </form>
  </aside>
</div>
<?php page_footer(); ?>
