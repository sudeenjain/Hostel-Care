<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');
$hid = (int) $user['hostel_id'];
$h = hostel_get($hid);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    [$blocksText, $blocksErr] = hostel_parse_blocks(post('blocks'));
    $name = post('name');
    $address = post('address');
    $phone = post('hostel_phone');
    if ($blocksErr) {
        $errors[] = $blocksErr;
    }
    if ($blocksText === '' && !$blocksErr) {
        $errors[] = 'Add at least one block.';
    }
    if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
        $errors[] = 'Enter the hostel name.';
    }
    if (mb_strlen($address) < 5 || mb_strlen($address) > 300) {
        $errors[] = 'Enter the address.';
    }
    if (!$errors) {
        q('UPDATE hostels SET name = ?, address = ?, hostel_phone = ?, blocks = ?, registration_open = ? WHERE id = ?',
            [$name, $address, $phone ?: null, $blocksText, post('registration_open') === '1' ? 1 : 0, $hid]);
        flash('ok', 'Hostel details saved.');
        redirect('warden/hostel.php');
    }
}
page_header('Hostel', $user);
?>
<h1>Hostel details</h1>
<?php foreach ($errors as $err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endforeach; ?>
<div class="card narrow">
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Hostel name<input name="name" required maxlength="120" value="<?= e($_POST['name'] ?? $h['name']) ?>"></label>
    <label>Address<input name="address" required maxlength="300" value="<?= e($_POST['address'] ?? $h['address']) ?>"></label>
    <label>Hostel phone (optional)<input name="hostel_phone" maxlength="30" value="<?= e($_POST['hostel_phone'] ?? $h['hostel_phone']) ?>"></label>
    <label>Blocks (one per line, for example: A, B, C or Ganga, Yamuna). Students pick their block from this list.
      <textarea name="blocks" rows="5" required><?= e($_POST['blocks'] ?? implode("\n", hostel_blocks_list($h))) ?></textarea></label>
    <label class="check"><input type="checkbox" name="registration_open" value="1" <?= (!isset($_POST['blocks']) ? (int) $h['registration_open'] : post('registration_open') === '1') ? 'checked' : '' ?>> New students may create accounts when they scan the QR code</label>
    <button class="btn" type="submit">Save</button>
  </form>
</div>
<?php page_footer(); ?>
