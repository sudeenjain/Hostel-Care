<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = post('action');
  if ($action === 'contact') {
    $raw = post('phone');
    $phone = $raw === '' ? null : sms_normalize_phone($raw);
    $opt = post('sms_opt_in') === '1' ? 1 : 0;
    if ($raw !== '' && $phone === null) {
      flash('err', 'Enter a valid mobile number, for example 9876543210.');
    } elseif ($opt && $phone === null) {
      flash('err', 'Enter a mobile number to receive SMS updates.');
    } else {
      q('UPDATE users SET phone = ?, sms_opt_in = ? WHERE id = ?', [$phone, $opt, $user['id']]);
      flash('ok', 'Contact settings saved.');
    }
  } elseif ($action === 'password') {
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
      flash('err', 'Your current password is incorrect.');
    } elseif (strlen($new) < (int) cfg('rules.min_password')) {
      flash('err', 'New password must be at least ' . cfg('rules.min_password') . ' characters.');
    } elseif ($new !== (string) ($_POST['new_password2'] ?? '')) {
      flash('err', 'The two new passwords do not match.');
    } else {
      q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
      flash('ok', 'Password changed.');
    }
  }
  redirect('profile.php');
}
$minPw = (int) cfg('rules.min_password');
$initial = strtoupper(mb_substr(trim((string) $user['name']), 0, 1)) ?: '?';
page_header('Profile', $user);
?>
<style>
  .pf {
    --blue: #2f80ed;
    --blue-d: #1b64c9;
    --sky: #eaf4ff;
    --sky2: #d6eaff;
    --ink: #0f2742;
    --muted: #5b6f87;
    --line: #d9e6f5;
    --err: #d64545;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    max-width: 1000px;
    margin: 0 auto;
  }

  .pf * {
    box-sizing: border-box;
  }

  .pf-head {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
    padding: 28px 32px;
    margin-bottom: 22px;
    border-radius: 26px;
    background: linear-gradient(135deg, #d6eaff, #eaf4ff 60%, #fff);
    border: 1px solid var(--line);
  }

  .pf-head::before {
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

  .pf-head>* {
    position: relative;
  }

  .pf-av {
    flex: 0 0 72px;
    width: 72px;
    height: 72px;
    display: grid;
    place-items: center;
    border-radius: 22px;
    color: #fff;
    font-size: 1.9rem;
    font-weight: 800;
    background: linear-gradient(135deg, #4a9bff, var(--blue-d));
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
  }

  .pf-head h1 {
    margin: 0 0 8px;
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    font-weight: 800;
    letter-spacing: -.02em;
  }

  .pf-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .pf-chips span {
    padding: 5px 12px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--sky2);
    color: var(--blue-d);
    font-weight: 600;
    font-size: .8rem;
  }

  .pf-chips .role {
    text-transform: capitalize;
    background: var(--blue);
    border-color: var(--blue);
    color: #fff;
  }

  .pf-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
    align-items: start;
  }

  .pf-stack {
    display: grid;
    gap: 22px;
  }

  .pf-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 22px;
    padding: 28px;
    box-shadow: 0 12px 36px rgba(47, 128, 237, .10);
  }

  .pf-card h2 {
    margin: 0 0 6px;
    font-size: 1.15rem;
    font-weight: 700;
  }

  .pf-card .sub {
    margin: 0 0 18px;
    color: var(--muted);
    font-size: .9rem;
    line-height: 1.5;
  }

  .pf dl {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0 20px;
    margin: 0;
  }

  .pf dt {
    padding: 12px 0;
    border-top: 1px solid #edf3fa;
    color: var(--muted);
    font-size: .82rem;
    font-weight: 600;
  }

  .pf dd {
    margin: 0;
    padding: 11px 0;
    border-top: 1px solid #edf3fa;
    font-size: .93rem;
    font-weight: 600;
    text-align: right;
    overflow-wrap: anywhere;
  }

  .pf dt:first-of-type,
  .pf dt:first-of-type+dd {
    border-top: 0;
  }

  .pf form {
    display: grid;
    gap: 16px;
    margin: 0;
  }

  .pf label {
    display: grid;
    gap: 7px;
    font-size: .86rem;
    font-weight: 600;
  }

  .pf input:not([type=checkbox]) {
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

  .pf input::placeholder {
    color: #9db0c6;
  }

  .pf input:focus {
    background: #fff;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .15);
  }

  .pf input:user-invalid {
    border-color: var(--err);
  }

  .pf .check {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 16px;
    background: var(--sky);
    border-radius: 14px;
    font-weight: 400;
    cursor: pointer;
  }

  .pf .check input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: var(--blue);
    flex: 0 0 auto;
  }

  .pf .check span {
    font-size: .88rem;
    color: #3d5573;
    line-height: 1.5;
  }

  .pf .note {
    margin: 0;
    padding: 12px 14px;
    border-radius: 12px;
    background: #fff8e8;
    border: 1px solid #f4e1b0;
    color: #8a5a00;
    font-size: .85rem;
    line-height: 1.5;
  }

  .pf .pw {
    position: relative;
    display: block;
  }

  .pf .pw input {
    padding-right: 66px;
  }

  .pf .pw button {
    position: absolute;
    right: 8px;
    top: 50%;
    translate: 0 -50%;
    padding: 6px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--blue-d);
    font: inherit;
    font-size: .8rem;
    font-weight: 700;
    cursor: pointer;
  }

  .pf .pw button:hover {
    background: var(--sky);
  }

  .pf-meter {
    height: 6px;
    border-radius: 99px;
    background: var(--sky2);
    overflow: hidden;
  }

  .pf-meter i {
    display: block;
    height: 100%;
    width: 0;
    border-radius: 99px;
    transition: width .25s, background .25s;
  }

  .pf-hint {
    min-height: 1.1em;
    margin-top: -4px;
    font-size: .8rem;
    font-weight: 500;
    color: var(--muted);
  }

  .pf-hint.bad {
    color: var(--err);
  }

  .pf-hint.good {
    color: #1f9d57;
  }

  .pf-btn {
    padding: 14px 26px;
    border: 0;
    border-radius: 14px;
    cursor: pointer;
    font: inherit;
    font-size: .97rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
    transition: transform .2s, opacity .2s;
  }

  .pf-btn:hover {
    transform: translateY(-2px);
  }

  .pf-btn:disabled {
    opacity: .65;
    cursor: wait;
    transform: none;
  }

  @media (max-width:820px) {
    .pf-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width:520px) {

    .pf-head,
    .pf-card {
      padding: 22px 18px;
    }

    .pf-btn {
      width: 100%;
    }
  }
</style>

<div class="pf">
  <div class="pf-head">
    <div class="pf-av" aria-hidden="true"><?= e($initial) ?></div>
    <div>
      <h1><?= e($user['name']) ?></h1>
      <div class="pf-chips">
        <span class="role"><?= e($user['role']) ?></span>
        <?php if (!empty($user['hostel_name'])): ?><span><?= e($user['hostel_name']) ?></span><?php endif; ?>
        <?php if ($user['block']): ?><span>Block <?= e($user['block']) ?>, Room <?= e($user['room']) ?></span><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="pf-grid">
    <div class="pf-stack">
      <div class="pf-card">
        <h2>Account</h2>
        <p class="sub">Your details as registered with your hostel.</p>
        <dl>
          <dt>Name</dt>
          <dd><?= e($user['name']) ?></dd>
          <?php if ($user['role'] === 'student'): ?><dt>Hostel ID</dt>
            <dd><?= e($user['login_id']) ?></dd><?php endif; ?><?php if (!empty($user['hostel_name'])): ?><dt>Hostel</dt>
            <dd><?= e($user['hostel_name']) ?></dd><?php endif; ?>
          <dt>Email</dt>
          <dd><?= e($user['email']) ?></dd>
          <dt>Role</dt>
          <dd style="text-transform:capitalize"><?= e($user['role']) ?></dd>
          <?php if ($user['block']): ?><dt>Room</dt>
            <dd>Block <?= e($user['block']) ?>, Room <?= e($user['room']) ?></dd><?php endif; ?>
        </dl>
      </div>

      <div class="pf-card">
        <h2>Mobile and SMS</h2>
        <p class="sub">Add your number to get status updates by SMS.</p>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="contact">
          <label>Mobile number<input name="phone" inputmode="tel" autocomplete="tel" placeholder="9876543210" value="<?= e($user['phone'] ?? '') ?>"></label>
          <label class="check"><input type="checkbox" name="sms_opt_in" value="1" <?= $user['sms_opt_in'] ? 'checked' : '' ?>>
            <span>Send me SMS updates. Messages contain only the ticket number and status, never the complaint details.</span></label>
          <?php if (!sms_enabled()): ?><p class="note">SMS sending is not switched on for this installation yet. Your choice is saved and will apply once it is.</p><?php endif; ?>
          <div><button class="pf-btn" type="submit">Save</button></div>
        </form>
      </div>
    </div>

    <div class="pf-card">
      <h2>Change password</h2>
      <p class="sub">Use at least <?= $minPw ?> characters. You stay signed in on this device.</p>
      <form method="post" id="pfPwForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <label>Current password
          <span class="pw"><input type="password" name="current_password" id="pfCur" required autocomplete="current-password"><button type="button" data-toggle="pfCur">Show</button></span>
        </label>
        <label>New password
          <span class="pw"><input type="password" name="new_password" id="pfNew" required minlength="<?= $minPw ?>" autocomplete="new-password"><button type="button" data-toggle="pfNew">Show</button></span>
        </label>
        <div class="pf-meter" aria-hidden="true"><i id="pfBar"></i></div>
        <label>Repeat new password
          <span class="pw"><input type="password" name="new_password2" id="pfNew2" required autocomplete="new-password"><button type="button" data-toggle="pfNew2">Show</button></span>
        </label>
        <div class="pf-hint" id="pfHint" aria-live="polite"></div>
        <div><button class="pf-btn" type="submit" id="pfBtn">Change password</button></div>
      </form>
    </div>
  </div>
</div>

<script>
  (function() {
    var f = document.getElementById('pfPwForm'),
      a = document.getElementById('pfNew'),
      b = document.getElementById('pfNew2'),
      bar = document.getElementById('pfBar'),
      hint = document.getElementById('pfHint'),
      btn = document.getElementById('pfBtn'),
      min = <?= $minPw ?>;
    document.querySelectorAll('[data-toggle]').forEach(function(t) {
      t.addEventListener('click', function() {
        var i = document.getElementById(t.getAttribute('data-toggle')),
          s = i.type === 'password';
        i.type = s ? 'text' : 'password';
        t.textContent = s ? 'Hide' : 'Show';
      });
    });

    function strength(v) {
      var s = 0;
      if (v.length >= min) s++;
      if (v.length >= min + 4) s++;
      if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
      if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
      return v ? Math.max(1, s) : 0;
    }

    function update() {
      var s = strength(a.value),
        c = ['#e4eefb', '#e5534b', '#f0a43a', '#4a9bff', '#1f9d57'];
      bar.style.width = (s * 25) + '%';
      bar.style.background = c[s];
      if (b.value && a.value !== b.value) {
        hint.textContent = 'Passwords do not match yet.';
        hint.className = 'pf-hint bad';
      } else if (b.value) {
        hint.textContent = 'Passwords match.';
        hint.className = 'pf-hint good';
      } else {
        hint.textContent = '';
        hint.className = 'pf-hint';
      }
    }
    a.addEventListener('input', update);
    b.addEventListener('input', update);
    f.addEventListener('submit', function(e) {
      if (!f.checkValidity()) {
        e.preventDefault();
        f.reportValidity();
        return;
      }
      if (a.value !== b.value) {
        e.preventDefault();
        update();
        b.focus();
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Changing...';
    });
  })();
</script>
<?php page_footer(); ?>