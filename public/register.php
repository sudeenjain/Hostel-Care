<?php
require __DIR__ . '/../includes/bootstrap.php';

/* Student registration always belongs to one hostel, reached through its QR code. */
$token = get('c') ?: post('c');
$h = hostel_by_qr($token);

/* Shared look for every state of this page (scoped under .rg). */
$rgStyle = <<<'CSS'
<style>
  .rg { --blue:#2f80ed; --blue-d:#1b64c9; --sky:#eaf4ff; --sky2:#d6eaff; --ink:#0f2742; --muted:#5b6f87; --line:#d9e6f5; --err:#d64545;
        font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color:var(--ink); }
  .rg * { box-sizing:border-box; }
  .rg-wrap { position:relative; margin-inline:calc(50% - 50vw); padding:56px 20px 80px; overflow:hidden;
    background:linear-gradient(180deg,#d6eaff 0%,#eaf4ff 55%,#fff 100%); }
  .rg-glow { position:absolute; border-radius:50%; filter:blur(110px); pointer-events:none; }
  .rg-glow.g1 { width:460px; height:460px; left:-120px; top:-140px; background:#fff; opacity:.8; }
  .rg-glow.g2 { width:520px; height:520px; right:-120px; top:160px; background:#7fb8ff; opacity:.4; }
  .rg-grid { position:relative; z-index:1; display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.1fr); gap:56px;
    max-width:1100px; margin:0 auto; align-items:start; }

  /* left intro panel */
  .rg-intro { padding-top:24px; position:sticky; top:24px; }
  .rg-badge { display:inline-flex; padding:8px 16px; border-radius:999px; background:#fff; border:1px solid var(--sky2);
    box-shadow:0 4px 16px rgba(47,128,237,.12); color:var(--blue-d); font-size:12px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; }
  .rg-intro h1 { margin:20px 0 14px; font-size:clamp(2rem,4.2vw,3rem); line-height:1.1; font-weight:800; letter-spacing:-.02em; }
  .rg-intro h1 span { color:var(--blue); }
  .rg-intro > p { margin:0 0 30px; color:#3d5573; font-size:1.05rem; line-height:1.65; max-width:440px; }
  .rg-steps { list-style:none; margin:0; padding:0; display:grid; gap:14px; }
  .rg-steps li { display:flex; gap:14px; align-items:center; padding:14px 16px; background:rgba(255,255,255,.75);
    border:1px solid var(--line); border-radius:16px; backdrop-filter:blur(6px); }
  .rg-steps b { flex:0 0 38px; height:38px; display:grid; place-items:center; border-radius:12px; background:var(--sky); color:var(--blue); font-size:.9rem; }
  .rg-steps span { color:var(--muted); font-size:.92rem; line-height:1.4; }
  .rg-steps strong { display:block; color:var(--ink); font-size:.97rem; }

  /* form card */
  .rg-card { background:#fff; border:1px solid var(--line); border-radius:24px; padding:34px;
    box-shadow:0 18px 50px rgba(47,128,237,.15); }
  .rg-card h2 { margin:0 0 4px; font-size:1.5rem; font-weight:700; }
  .rg-card .rg-sub { margin:0 0 22px; color:var(--muted); font-size:.93rem; }
  .rg-sec { margin:26px 0 12px; display:flex; align-items:center; gap:10px; font-size:.78rem; font-weight:700;
    letter-spacing:.08em; text-transform:uppercase; color:var(--blue-d); }
  .rg-sec::after { content:""; flex:1; height:1px; background:var(--line); }
  .rg-sec:first-of-type { margin-top:0; }
  .rg form { display:grid; gap:16px; }
  .rg label { display:grid; gap:7px; font-size:.86rem; font-weight:600; color:var(--ink); }
  .rg label small { font-weight:400; color:var(--muted); line-height:1.4; }
  .rg input:not([type=checkbox]), .rg select { width:100%; padding:13px 14px; font:inherit; font-size:.95rem; font-weight:400; color:var(--ink);
    background:#f7fbff; border:1.5px solid var(--line); border-radius:12px; outline:0; transition:border-color .2s, box-shadow .2s, background .2s; }
  .rg input::placeholder { color:#9db0c6; }
  .rg input:focus, .rg select:focus { background:#fff; border-color:var(--blue); box-shadow:0 0 0 4px rgba(47,128,237,.15); }
  .rg input:user-invalid, .rg select:user-invalid { border-color:var(--err); }
  .rg .row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
  .rg .check { display:flex; gap:12px; align-items:flex-start; padding:14px; background:var(--sky); border-radius:12px; font-weight:400; cursor:pointer; }
  .rg .check input { width:18px; height:18px; margin-top:2px; accent-color:var(--blue); flex:0 0 auto; }
  .rg .check span { font-size:.86rem; color:#3d5573; line-height:1.5; }
  .rg .pw { position:relative; }
  .rg .pw input { padding-right:64px; }
  .rg .pw button { position:absolute; right:8px; top:50%; translate:0 -50%; padding:6px 10px; border:0; border-radius:8px;
    background:transparent; color:var(--blue-d); font:inherit; font-size:.8rem; font-weight:700; cursor:pointer; }
  .rg .pw button:hover { background:var(--sky); }
  .rg-meter { height:6px; border-radius:99px; background:var(--sky2); overflow:hidden; }
  .rg-meter i { display:block; height:100%; width:0; border-radius:99px; transition:width .25s, background .25s; }
  .rg-hint { min-height:1.1em; margin-top:-2px; font-size:.8rem; font-weight:500; color:var(--muted); }
  .rg-hint.bad { color:var(--err); } .rg-hint.good { color:#1f9d57; }
  .rg .btn-main { width:100%; margin-top:6px; padding:15px 24px; border:0; border-radius:14px; cursor:pointer; font:inherit; font-size:1rem; font-weight:700; color:#fff;
    background:linear-gradient(180deg,#4a9bff,var(--blue-d)); box-shadow:0 10px 26px rgba(47,128,237,.35); transition:transform .2s, box-shadow .2s, opacity .2s; }
  .rg .btn-main:hover { transform:translateY(-2px); }
  .rg .btn-main:disabled { opacity:.65; cursor:wait; transform:none; }
  .rg-foot { margin:22px 0 0; text-align:center; color:var(--muted); font-size:.92rem; }
  .rg-foot a { color:var(--blue-d); font-weight:700; text-decoration:none; }
  .rg-foot a:hover { text-decoration:underline; }
  .rg-err { display:flex; gap:10px; padding:12px 14px; border-radius:12px; background:#fff1f1; border:1px solid #f5c6c6; color:#a32b2b; font-size:.9rem; line-height:1.45; }
  .rg-err::before { content:"!"; flex:0 0 22px; height:22px; display:grid; place-items:center; border-radius:50%; background:var(--err); color:#fff; font-size:.8rem; font-weight:800; }
  .rg-msg { max-width:520px; margin:0 auto; text-align:center; position:relative; z-index:1; }
  .rg-msg .rg-card { padding:44px 34px; }
  .rg-msg .ico { width:64px; height:64px; margin:0 auto 18px; display:grid; place-items:center; border-radius:18px; background:var(--sky); color:var(--blue); font-size:1.6rem; font-weight:800; }
  .rg-msg h1 { margin:0 0 10px; font-size:1.7rem; }
  .rg-msg p { margin:0; color:var(--muted); line-height:1.6; }

  @media (max-width:880px) {
    .rg-grid { grid-template-columns:1fr; gap:28px; }
    .rg-intro { position:static; padding-top:0; text-align:center; }
    .rg-intro > p { margin-inline:auto; }
    .rg-steps { display:none; }
  }
  @media (max-width:520px) { .rg-card { padding:24px 18px; } .rg .row2 { grid-template-columns:1fr; } }
</style>
CSS;

if (!$h) {
  http_response_code(404);
  page_header('Create account', null);
  echo $rgStyle;
  echo '<div class="rg"><div class="rg-wrap"><span class="rg-glow g1"></span><span class="rg-glow g2"></span>'
    . '<div class="rg-msg"><div class="rg-card"><div class="ico">QR</div>'
    . '<h1>Scan your hostel\'s QR code</h1>'
    . '<p>Student accounts are created from the QR code your warden shares.</p></div></div></div></div>';
  page_footer();
  exit;
}
if ($u = current_user()) {
  redirect(home_for($u));
}
$open = (int) $h['registration_open'] === 1;
$blocks = hostel_blocks_list($h);
$errors = [];
if ($open && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  if (!in_array(post('block'), $blocks, true)) {
    $errors[] = 'Choose your block from the list.';
  }
  if (($_POST['password'] ?? '') !== ($_POST['password2'] ?? null)) {
    $errors[] = 'The two passwords do not match.';
  }
  if (!$errors) {
    $newId = null;
    $errors = create_user([
      'hostel_id' => (int) $h['id'],
      'login_id' => post('login_id'),
      'name' => post('name'),
      'email' => post('email'),
      'phone' => post('phone'),
      'sms_opt_in' => post('sms_opt_in'),
      'block' => post('block'),
      'room' => post('room'),
      'password' => (string) $_POST['password'],
      'role' => 'student',
    ], $newId);
    if (!$errors) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $newId;
      flash('ok', 'Account created. You can now report a problem.');
      redirect('student/new.php');
    }
  }
}
$minPw = (int) cfg('rules.min_password');
page_header('Create account', null);
echo $rgStyle;
?>
<div class="rg">
  <div class="rg-wrap">
    <span class="rg-glow g1"></span><span class="rg-glow g2"></span>

    <?php if (!$open): ?>
      <div class="rg-msg">
        <div class="rg-card">
          <div class="ico">!</div>
          <h1><?= e($h['name']) ?></h1>
          <p>New accounts are closed. Ask your warden.</p>
          <p class="rg-foot">Already registered? <a href="<?= e(url('h.php?c=' . $token)) ?>">Sign in</a></p>
        </div>
      </div>

    <?php else: ?>
      <div class="rg-grid">
        <!-- Intro -->
        <aside class="rg-intro">
          <div style="margin-bottom: 16px;">
            <span style="display:inline-flex; align-items:center; background:#ffffff; padding:6px 14px; border-radius:12px; box-shadow:0 4px 14px rgba(47,128,237,0.12); border:1px solid #d9e6f5;">
              <img src="<?= e(url('assets/img/logo.png')) ?>" alt="HostelCare" style="height:32px; width:auto; display:block;">
            </span>
          </div>
          <span class="rg-badge">Student registration</span>
          <h1>Join <span><?= e($h['name']) ?></span></h1>
          <p>Create your account once, then report hostel problems in seconds and follow every fix in real time.</p>
          <ol class="rg-steps">
            <li><b>1</b><span><strong>Scan the QR code</strong>You are already here, nice.</span></li>
            <li><b>2</b><span><strong>Create your account</strong>Takes about a minute.</span></li>
            <li><b>3</b><span><strong>Report and track</strong>See status updates on every complaint.</span></li>
          </ol>
        </aside>

        <!-- Form -->
        <div class="rg-card">
          <h2>Create your account</h2>
          <p class="rg-sub">Fields marked as optional can be skipped.</p>

          <?php foreach ($errors as $err): ?><div class="rg-err flash flash-err" role="alert" style="margin-bottom:12px"><?= e($err) ?></div><?php endforeach; ?>

          <form method="post" id="rgForm" novalidate>
            <?= csrf_field() ?><input type="hidden" name="c" value="<?= e($token) ?>">

            <div class="rg-sec">About you</div>
            <label>Full name<input name="name" required autocomplete="name" placeholder="As on your ID card" value="<?= e(post('name')) ?>"></label>
            <label>Hostel ID<small>The ID you use in this hostel, such as a roll or admission number.</small>
              <input name="login_id" required autocomplete="username" value="<?= e(post('login_id')) ?>"></label>

            <div class="rg-sec">Contact</div>
            <label>Email<input type="email" name="email" required autocomplete="email" placeholder="you@example.com" value="<?= e(post('email')) ?>"></label>
            <label>Mobile number (optional)<input name="phone" inputmode="tel" autocomplete="tel" placeholder="9876543210" value="<?= e(post('phone')) ?>"></label>
            <label class="check"><input type="checkbox" name="sms_opt_in" value="1" <?= post('sms_opt_in') ? 'checked' : '' ?>><span>Send me SMS updates about my complaints. Messages contain only the ticket number and status.</span></label>

            <div class="rg-sec">Where you stay</div>
            <div class="row2">
              <label>Block<select name="block" required>
                  <option value="">Choose</option>
                  <?php foreach ($blocks as $b): ?><option value="<?= e($b) ?>" <?= post('block') === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
                </select></label>
              <label>Room<input name="room" required placeholder="e.g. 204" value="<?= e(post('room')) ?>"></label>
            </div>

            <div class="rg-sec">Security</div>
            <label>Password<small>At least <?= $minPw ?> characters.</small>
              <span class="pw"><input type="password" name="password" id="pw1" required minlength="<?= $minPw ?>" autocomplete="new-password"><button type="button" data-toggle="pw1">Show</button></span>
            </label>
            <div class="rg-meter" aria-hidden="true"><i id="pwBar"></i></div>
            <label>Repeat password
              <span class="pw"><input type="password" name="password2" id="pw2" required autocomplete="new-password"><button type="button" data-toggle="pw2">Show</button></span>
            </label>
            <div class="rg-hint" id="pwHint" aria-live="polite"></div>

            <button class="btn btn-main" type="submit" id="rgSubmit">Create account</button>
          </form>

          <p class="rg-foot">Already registered? <a href="<?= e(url('h.php?c=' . $token)) ?>">Sign in</a></p>
        </div>
      </div>

      <script>
        (function() {
          var f = document.getElementById('rgForm'),
            p1 = document.getElementById('pw1'),
            p2 = document.getElementById('pw2'),
            bar = document.getElementById('pwBar'),
            hint = document.getElementById('pwHint'),
            sub = document.getElementById('rgSubmit'),
            min = <?= $minPw ?>;

          document.querySelectorAll('[data-toggle]').forEach(function(b) {
            b.addEventListener('click', function() {
              var i = document.getElementById(b.getAttribute('data-toggle'));
              var show = i.type === 'password';
              i.type = show ? 'text' : 'password';
              b.textContent = show ? 'Hide' : 'Show';
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
            var s = strength(p1.value),
              colors = ['#e4eefb', '#e5534b', '#f0a43a', '#4a9bff', '#1f9d57'];
            bar.style.width = (s * 25) + '%';
            bar.style.background = colors[s];
            if (p2.value && p1.value !== p2.value) {
              hint.textContent = 'Passwords do not match yet.';
              hint.className = 'rg-hint bad';
            } else if (p2.value) {
              hint.textContent = 'Passwords match.';
              hint.className = 'rg-hint good';
            } else {
              hint.textContent = '';
              hint.className = 'rg-hint';
            }
          }
          p1.addEventListener('input', update);
          p2.addEventListener('input', update);

          f.addEventListener('submit', function(e) {
            if (!f.checkValidity()) {
              e.preventDefault();
              f.reportValidity();
              return;
            }
            if (p1.value !== p2.value) {
              e.preventDefault();
              update();
              p2.focus();
              return;
            }
            sub.disabled = true;
            sub.textContent = 'Creating account...';
          });
        })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>