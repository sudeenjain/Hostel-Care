<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('student');

$hostel = hostel_get((int) $user['hostel_id']);
$cats = categories_active((int) $user['hostel_id']);
$blocks = hostel_blocks_list($hostel);
$fields = form_fields((int) $user['hostel_id']);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $id = create_complaint($user, $_POST, normalize_files($_FILES['photos'] ?? null), $errors);
  if ($id) {
    flash('ok', 'Complaint registered. Your ticket number is ' . ticket_no($id) . '.');
    redirect('student/view.php?id=' . $id);
  }
}
$defBlock = $_POST['block'] ?? ($user['block'] ?? '');
$defPlace = $_POST['place'] ?? ($user['room'] ? 'Room ' . $user['room'] : '');
page_header('New complaint', $user);
?>
<style>
  .nc {
    --blue: #2f80ed;
    --blue-d: #1b64c9;
    --sky: #eaf4ff;
    --sky2: #d6eaff;
    --ink: #0f2742;
    --muted: #5b6f87;
    --line: #d9e6f5;
    --err: #d64545;
    --warn: #d97706;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    max-width: 820px;
    margin: 0 auto;
  }

  .nc * {
    box-sizing: border-box;
  }

  .nc-head {
    position: relative;
    overflow: hidden;
    padding: 30px 32px;
    border-radius: 26px;
    margin-bottom: 22px;
    background: linear-gradient(135deg, #d6eaff, #eaf4ff 60%, #fff);
    border: 1px solid var(--line);
  }

  .nc-head::before {
    content: "";
    position: absolute;
    width: 340px;
    height: 340px;
    right: -90px;
    top: -150px;
    border-radius: 50%;
    background: #7fb8ff;
    opacity: .35;
    filter: blur(90px);
  }

  .nc-head>* {
    position: relative;
  }

  .nc-head h1 {
    margin: 0 0 6px;
    font-size: clamp(1.6rem, 3.2vw, 2.2rem);
    font-weight: 800;
    letter-spacing: -.02em;
  }

  .nc-head p {
    margin: 0;
    color: #3d5573;
    font-size: .97rem;
  }

  .nc form {
    display: grid;
    gap: 20px;
  }

  .nc-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 22px;
    padding: 28px;
    box-shadow: 0 12px 36px rgba(47, 128, 237, .10);
  }

  .nc-sec {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 18px;
  }

  .nc-sec b {
    flex: 0 0 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: var(--sky);
    color: var(--blue);
    font-weight: 800;
    font-size: .9rem;
  }

  .nc-sec h2 {
    margin: 0;
    font-size: 1.12rem;
    font-weight: 700;
  }

  .nc-fields {
    display: grid;
    gap: 16px;
  }

  .nc label {
    display: grid;
    gap: 7px;
    font-size: .86rem;
    font-weight: 600;
  }

  .nc label small {
    font-weight: 400;
    color: var(--muted);
    line-height: 1.45;
  }

  .nc input:not([type=checkbox]):not([type=file]),
  .nc select,
  .nc textarea {
    width: 100%;
    padding: 13px 14px;
    font: inherit;
    font-size: .95rem;
    font-weight: 400;
    color: var(--ink);
    background: #f7fbff;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    outline: 0;
    transition: border-color .2s, box-shadow .2s, background .2s;
  }

  .nc textarea {
    resize: vertical;
    min-height: 110px;
  }

  .nc input::placeholder,
  .nc textarea::placeholder {
    color: #9db0c6;
  }

  .nc input:focus,
  .nc select:focus,
  .nc textarea:focus {
    background: #fff;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .15);
  }

  .nc input:user-invalid,
  .nc select:user-invalid,
  .nc textarea:user-invalid {
    border-color: var(--err);
  }

  .nc .row2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  #cat-hint {
    margin: -6px 0 0;
    padding: 0;
    font-size: .84rem;
    color: var(--blue-d);
    font-weight: 500;
  }

  #cat-hint:empty {
    display: none;
  }

  #description-count {
    text-align: right;
  }

  /* photo drop area */
  .nc-drop {
    display: block;
    padding: 22px;
    text-align: center;
    border: 2px dashed var(--sky2);
    border-radius: 16px;
    background: #f7fbff;
    cursor: pointer;
    transition: border-color .2s, background .2s;
  }

  .nc-drop:hover,
  .nc-drop.over {
    border-color: var(--blue);
    background: var(--sky);
  }

  .nc-drop strong {
    display: block;
    color: var(--blue-d);
    font-size: .95rem;
  }

  .nc-drop span {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: .82rem;
    font-weight: 400;
  }

  .nc-drop input {
    margin-top: 12px;
    max-width: 100%;
    font-size: .85rem;
  }

  .nc-previews {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 12px;
  }

  .nc-previews:empty {
    display: none;
  }

  .nc-previews figure {
    margin: 0;
    width: 84px;
  }

  .nc-previews img {
    width: 84px;
    height: 84px;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid var(--line);
    display: block;
  }

  .nc-previews figcaption {
    margin-top: 4px;
    font-size: .7rem;
    color: var(--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .nc-warn {
    color: var(--warn);
    font-size: .83rem;
    font-weight: 600;
  }

  .nc .check {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 16px;
    background: var(--sky);
    border-radius: 14px;
    font-weight: 400;
    cursor: pointer;
  }

  .nc .check input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: var(--blue);
    flex: 0 0 auto;
  }

  .nc .check span {
    font-size: .88rem;
    color: #3d5573;
    line-height: 1.5;
  }

  .nc-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
  }

  .nc-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 15px 30px;
    border-radius: 14px;
    border: 1.5px solid transparent;
    cursor: pointer;
    font: inherit;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    color: #fff;
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
    transition: transform .2s, opacity .2s;
  }

  .nc-btn:hover {
    transform: translateY(-2px);
  }

  .nc-btn:disabled {
    opacity: .65;
    cursor: wait;
    transform: none;
  }

  .nc-btn.light {
    color: var(--blue-d);
    background: #fff;
    border-color: var(--sky2);
    box-shadow: none;
  }

  .nc-btn.light:hover {
    background: var(--sky);
  }

  .nc-err {
    display: flex;
    gap: 10px;
    padding: 12px 14px;
    margin-bottom: 4px;
    border-radius: 12px;
    background: #fff1f1;
    border: 1px solid #f5c6c6;
    color: #a32b2b;
    font-size: .9rem;
    line-height: 1.45;
  }

  .nc-err::before {
    content: "!";
    flex: 0 0 22px;
    height: 22px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--err);
    color: #fff;
    font-size: .8rem;
    font-weight: 800;
  }

  @media (max-width:640px) {
    .nc .row2 {
      grid-template-columns: 1fr;
    }

    .nc-card {
      padding: 22px 18px;
    }

    .nc-head {
      padding: 22px 18px;
    }

    .nc-btn {
      width: 100%;
    }
  }
</style>

<div class="nc">
  <div class="nc-head">
    <h1>Raise a complaint</h1>
    <p>Tell us what is wrong. You will get a ticket number and can follow every update.</p>
  </div>

  <?php if (!$cats): ?>
    <div class="nc-card"><?= empty_state('No complaint categories exist yet. Ask your warden to finish setting up the hostel.') ?></div>
  <?php else: ?>

    <?php foreach ($errors as $err): ?><div class="nc-err flash flash-err" role="alert"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" id="ncForm">
      <?= csrf_field() ?>

      <div class="nc-card">
        <div class="nc-sec"><b>1</b>
          <h2>What is it about?</h2>
        </div>
        <div class="nc-fields">
          <label>Category
            <select name="category_id" id="category" required>
              <option value="">Choose a category</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int) $c['id'] ?>" data-priority="<?= e($c['default_priority']) ?>" data-sla="<?= (int) $c['sla_hours'] ?>"
                  <?= (string) ($_POST['category_id'] ?? '') === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <p class="muted small" id="cat-hint"></p>

          <label>Short title
            <input name="title" required minlength="5" maxlength="160" placeholder="What is the problem in a few words?" value="<?= e($_POST['title'] ?? '') ?>">
          </label>
        </div>
      </div>

      <div class="nc-card">
        <div class="nc-sec"><b>2</b>
          <h2>Where and what happened?</h2>
        </div>
        <div class="nc-fields">
          <div class="row2">
            <label>Block<?php if ($blocks): ?>
              <select name="block" required>
                <option value="">Choose</option><?php foreach ($blocks as $b): ?><option value="<?= e($b) ?>" <?= $defBlock === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
              </select>
            <?php else: ?><input name="block" value="<?= e($defBlock) ?>"><?php endif; ?></label>
            <label>Room or place<input name="place" required maxlength="100" placeholder="Room 204, corridor, mess..." value="<?= e($defPlace) ?>"></label>
          </div>
          <label>Describe the problem
            <textarea name="description" id="description" required minlength="10" maxlength="4000" rows="5" data-count="description-count"
              placeholder="When did it start? How does it affect you?"><?= e($_POST['description'] ?? '') ?></textarea>
            <small class="muted" id="description-count"></small>
          </label>

          <?php foreach ($fields as $f): $v = $_POST['f_' . $f['id']] ?? ''; ?>
            <label><?= e($f['label']) ?><?= $f['is_required'] ? '' : ' <small class="muted">(optional)</small>' ?>
              <?php if ($f['field_type'] === 'dropdown'): ?>
                <select name="f_<?= (int) $f['id'] ?>" <?= $f['is_required'] ? 'required' : '' ?>>
                  <option value="">Choose</option>
                  <?php foreach (field_options($f) as $o): ?><option value="<?= e($o) ?>" <?= $v === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
                </select>
              <?php elseif ($f['field_type'] === 'textarea'): ?>
                <textarea name="f_<?= (int) $f['id'] ?>" rows="3" maxlength="2000" <?= $f['is_required'] ? 'required' : '' ?>><?= e($v) ?></textarea>
              <?php else: ?>
                <input name="f_<?= (int) $f['id'] ?>" maxlength="200" value="<?= e($v) ?>" <?= $f['is_required'] ? 'required' : '' ?>>
              <?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="nc-card">
        <div class="nc-sec"><b>3</b>
          <h2>Priority and photos</h2>
        </div>
        <div class="nc-fields">
          <label>Priority
            <select name="priority" id="priority">
              <?php foreach (priority_labels() as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= ($_POST['priority'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
              <?php endforeach; ?>
            </select>
            <small class="muted">Pick "High" only if it affects safety, health or cannot wait.</small>
          </label>

          <div>
            <label class="nc-drop" id="drop" for="photos">
              <strong>Add photos (optional)</strong>
              <span>Up to <?= (int) cfg('rules.max_files') ?> files, JPG/PNG/WebP, <?= (int) cfg('rules.max_file_mb') ?> MB each</span>
              <input type="file" name="photos[]" id="photos" accept="image/jpeg,image/png,image/webp" multiple data-max="<?= (int) cfg('rules.max_files') ?>">
            </label>
            <div class="nc-previews" id="previews"></div>
            <div class="nc-warn" id="photoWarn" role="status"></div>
          </div>
        </div>
      </div>

      <div class="nc-card">
        <div class="nc-sec"><b>4</b>
          <h2>Privacy</h2>
        </div>
        <label class="check">
          <input type="checkbox" name="is_confidential" value="1" <?= !empty($_POST['is_confidential']) ? 'checked' : '' ?>>
          <span>Confidential: only the owner warden of your hostel can read this complaint. Assistant wardens see a hidden entry.</span>
        </label>
      </div>

      <div class="nc-actions">
        <button class="nc-btn" type="submit" id="ncSubmit">Submit complaint</button>
        <a class="nc-btn light" href="<?= e(url('student/dashboard.php')) ?>">Cancel</a>
      </div>
    </form>

    <script>
      (function() {
        var f = document.getElementById('ncForm'),
          btn = document.getElementById('ncSubmit'),
          inp = document.getElementById('photos'),
          box = document.getElementById('previews'),
          warn = document.getElementById('photoWarn'),
          drop = document.getElementById('drop'),
          max = parseInt(inp.getAttribute('data-max'), 10) || 0,
          urls = [];

        function render() {
          urls.forEach(function(u) {
            URL.revokeObjectURL(u);
          });
          urls = [];
          box.innerHTML = '';
          var files = Array.prototype.slice.call(inp.files || []);
          files.slice(0, 12).forEach(function(file) {
            var fig = document.createElement('figure'),
              img = document.createElement('img'),
              cap = document.createElement('figcaption'),
              u = URL.createObjectURL(file);
            urls.push(u);
            img.src = u;
            img.alt = '';
            cap.textContent = file.name;
            fig.appendChild(img);
            fig.appendChild(cap);
            box.appendChild(fig);
          });
          warn.textContent = (max && files.length > max) ? 'You chose ' + files.length + ' photos. Only ' + max + ' are allowed.' : '';
        }
        inp.addEventListener('change', render);
        ['dragenter', 'dragover'].forEach(function(ev) {
          drop.addEventListener(ev, function() {
            drop.classList.add('over');
          });
        });
        ['dragleave', 'drop'].forEach(function(ev) {
          drop.addEventListener(ev, function() {
            drop.classList.remove('over');
          });
        });

        f.addEventListener('submit', function(e) {
          if (!f.checkValidity()) {
            e.preventDefault();
            f.reportValidity();
            return;
          }
          if (max && inp.files && inp.files.length > max) {
            e.preventDefault();
            warn.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
            return;
          }
          btn.disabled = true;
          btn.textContent = 'Submitting...';
        });
      })();
    </script>

  <?php endif; ?>
</div>
<?php page_footer(); ?>