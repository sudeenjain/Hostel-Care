<?php
require __DIR__ . '/../../includes/bootstrap.php';

$token = get('t') ?: post('t');
$h = hostel_find_by_token('setup', $token);
$valid = $h && $h['status'] === 'approved' && !$h['owner_user_id'];
$errors = [];
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  [$uid, $errors] = hostel_owner_setup($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['password2'] ?? ''));
  if ($uid) {
    if (PHP_SAPI !== 'cli') {
      session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $uid;
    flash('ok', 'Welcome! Your hostel dashboard is ready. Finish the setup checklist, then publish to get your QR code.');
    redirect('warden/setup.php');
  }
}
$minPw = (int) cfg('rules.min_password');
page_header('Set up your hostel', null);
?>
<style>
  .ws {
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
  }

  .ws * {
    box-sizing: border-box;
  }

  .ws-wrap {
    position: relative;
    margin-inline: calc(50% - 50vw);
    padding: 64px 20px 90px;
    overflow: hidden;
    background: linear-gradient(180deg, #d6eaff 0%, #eaf4ff 55%, #fff 100%);
  }

  .ws-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(110px);
    pointer-events: none;
  }

  .ws-glow.g1 {
    width: 460px;
    height: 460px;
    left: -120px;
    top: -140px;
    background: #fff;
    opacity: .8;
  }

  .ws-glow.g2 {
    width: 520px;
    height: 520px;
    right: -120px;
    top: 160px;
    background: #7fb8ff;
    opacity: .4;
  }

  .ws-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 56px;
    max-width: 1020px;
    margin: 0 auto;
    align-items: center;
  }

  .ws-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--sky2);
    box-shadow: 0 4px 16px rgba(47, 128, 237, .12);
    color: var(--blue-d);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
  }

  .ws-badge::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1f9d57;
  }

  .ws-intro h1 {
    margin: 20px 0 12px;
    font-size: clamp(2rem, 4.2vw, 3rem);
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: -.02em;
  }

  .ws-intro h1 span {
    color: var(--blue);
  }

  .ws-intro>p {
    margin: 0 0 28px;
    color: #3d5573;
    font-size: 1.05rem;
    line-height: 1.65;
    max-width: 440px;
  }

  .ws-intro>p strong {
    color: var(--ink);
  }

  .ws-steps {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 12px;
  }

  .ws-steps li {
    display: flex;
    gap: 14px;
    align-items: center;
    padding: 14px 16px;
    background: rgba(255, 255, 255, .75);
    border: 1px solid var(--line);
    border-radius: 16px;
    backdrop-filter: blur(6px);
    color: var(--muted);
    font-size: .92rem;
    line-height: 1.4;
  }

  .ws-steps b {
    flex: 0 0 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: var(--sky);
    color: var(--blue);
  }

  .ws-steps strong {
    display: block;
    color: var(--ink);
    font-size: .97rem;
  }

  .ws-steps li.now b {
    background: var(--blue);
    color: #fff;
  }

  .ws-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 24px;
    padding: 34px;
    box-shadow: 0 18px 50px rgba(47, 128, 237, .15);
  }

  .ws-card h2 {
    margin: 0 0 6px;
    font-size: 1.45rem;
    font-weight: 700;
  }

  .ws-sub {
    margin: 0 0 20px;
    color: var(--muted);
    font-size: .93rem;
    line-height: 1.5;
  }

  .ws-email {
    display: flex;
    gap: 10px;
    align-items: center;
    margin-bottom: 22px;
    padding: 12px 14px;
    border-radius: 12px;
    background: var(--sky);
    border: 1px solid var(--sky2);
    color: var(--blue-d);
    font-weight: 600;
    font-size: .92rem;
    word-break: break-all;
  }

  .ws-email small {
    display: block;
    color: var(--muted);
    font-weight: 500;
    font-size: .75rem;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .ws form {
    display: grid;
    gap: 16px;
  }

  .ws label {
    display: grid;
    gap: 7px;
    font-size: .86rem;
    font-weight: 600;
  }

  .ws label small {
    font-weight: 400;
    color: var(--muted);
  }

  .ws input {
    width: 100%;
    padding: 13px 14px;
    padding-right: 66px;
    font: inherit;
    font-size: .95rem;
    color: var(--ink);
    background: #f7fbff;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    outline: 0;
    transition: border-color .2s, box-shadow .2s, background .2s;
  }

  .ws input:focus {
    background: #fff;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .15);
  }

  .ws input:user-invalid {
    border-color: var(--err);
  }

  .ws .pw {
    position: relative;
    display: block;
  }

  .ws .pw button {
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

  .ws .pw button:hover {
    background: var(--sky);
  }

  .ws-meter {
    height: 6px;
    border-radius: 99px;
    background: var(--sky2);
    overflow: hidden;
  }

  .ws-meter i {
    display: block;
    height: 100%;
    width: 0;
    border-radius: 99px;
    transition: width .25s, background .25s;
  }

  .ws-hint {
    min-height: 1.1em;
    margin-top: -4px;
    font-size: .8rem;
    font-weight: 500;
    color: var(--muted);
  }

  .ws-hint.bad {
    color: var(--err);
  }

  .ws-hint.good {
    color: #1f9d57;
  }

  .ws-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    margin-top: 4px;
    padding: 15px 24px;
    border: 0;
    border-radius: 14px;
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

  .ws-btn:hover {
    transform: translateY(-2px);
  }

  .ws-btn:disabled {
    opacity: .65;
    cursor: wait;
    transform: none;
  }

  .ws-btn.light {
    color: var(--blue-d);
    background: #fff;
    border: 1.5px solid var(--sky2);
    box-shadow: none;
  }

  .ws-btn.light:hover {
    background: var(--sky);
  }

  .ws-err {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
    padding: 12px 14px;
    border-radius: 12px;
    background: #fff1f1;
    border: 1px solid #f5c6c6;
    color: #a32b2b;
    font-size: .9rem;
    line-height: 1.45;
  }

  .ws-err::before {
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

  .ws-msg {
    position: relative;
    z-index: 1;
    max-width: 520px;
    margin: 0 auto;
    text-align: center;
  }

  .ws-msg .ws-card {
    padding: 44px 34px;
  }

  .ws-msg .ico {
    width: 64px;
    height: 64px;
    margin: 0 auto 18px;
    display: grid;
    place-items: center;
    border-radius: 18px;
    background: #fff1f1;
    color: var(--err);
    font-size: 1.6rem;
    font-weight: 800;
  }

  .ws-msg h1 {
    margin: 0 0 10px;
    font-size: 1.7rem;
  }

  .ws-msg p {
    margin: 0 0 22px;
    color: var(--muted);
    line-height: 1.6;
  }

  .ws-msg a:not(.ws-btn) {
    color: var(--blue-d);
    font-weight: 700;
  }

  @media (max-width:880px) {
    .ws-grid {
      grid-template-columns: 1fr;
      gap: 28px;
    }

    .ws-intro {
      text-align: center;
    }

    .ws-intro>p {
      margin-inline: auto;
    }

    .ws-steps {
      display: none;
    }
  }

  @media (max-width:520px) {
    .ws-card {
      padding: 24px 18px;
    }
  }
</style>

<div class="ws">
  <div class="ws-wrap">
    <span class="ws-glow g1"></span><span class="ws-glow g2"></span>

    <?php if (!$valid): ?>
      <div class="ws-msg">
        <div class="ws-card">
          <div class="ico">!</div>
          <h1>Link not valid</h1>
          <p>This setup link has expired or was already used. If you already chose a password, sign in. Otherwise ask the HostelCare administrator to send a new link.</p>
          <a class="ws-btn" href="<?= e(url('login.php')) ?>">Sign in</a>
        </div>
      </div>

    <?php else: ?>
      <div class="ws-grid">
        <aside class="ws-intro">
          <span class="ws-badge">Hostel approved</span>
          <h1>Welcome, <span><?= e($h['owner_name']) ?></span></h1>
          <p><strong><?= e($h['name']) ?></strong> has been approved. Choose a password to open your dashboard.</p>
          <ol class="ws-steps">
            <li class="now"><b>1</b><span><strong>Choose your password</strong>You are here.</span></li>
            <li><b>2</b><span><strong>Finish the setup checklist</strong>Complaint form, service providers and blocks.</span></li>
            <li><b>3</b><span><strong>Publish and get your QR code</strong>Print the poster for your students.</span></li>
          </ol>
        </aside>

        <div class="ws-card">
          <h2>Create your password</h2>
          <p class="ws-sub">You will use it with your email to sign in from now on.</p>
          <div class="ws-email">
            <div><small>Your login email</small><?= e($h['owner_email']) ?></div>
          </div>

          <?php foreach ($errors as $err): ?><div class="ws-err flash flash-err" role="alert"><?= e($err) ?></div><?php endforeach; ?>

          <form method="post" id="wsForm" novalidate>
            <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
            <label>Password<small>At least <?= $minPw ?> characters.</small>
              <span class="pw"><input type="password" name="password" id="pw1" required minlength="<?= $minPw ?>" autocomplete="new-password"><button type="button" data-toggle="pw1">Show</button></span>
            </label>
            <div class="ws-meter" aria-hidden="true"><i id="pwBar"></i></div>
            <label>Repeat password
              <span class="pw"><input type="password" name="password2" id="pw2" required autocomplete="new-password"><button type="button" data-toggle="pw2">Show</button></span>
            </label>
            <div class="ws-hint" id="pwHint" aria-live="polite"></div>
            <button class="ws-btn" type="submit" id="wsSubmit">Open my dashboard</button>
          </form>
        </div>
      </div>

      <script>
        (function() {
          var f = document.getElementById('wsForm'),
            p1 = document.getElementById('pw1'),
            p2 = document.getElementById('pw2'),
            bar = document.getElementById('pwBar'),
            hint = document.getElementById('pwHint'),
            sub = document.getElementById('wsSubmit'),
            min = <?= $minPw ?>;
          document.querySelectorAll('[data-toggle]').forEach(function(b) {
            b.addEventListener('click', function() {
              var i = document.getElementById(b.getAttribute('data-toggle')),
                show = i.type === 'password';
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
              hint.className = 'ws-hint bad';
            } else if (p2.value) {
              hint.textContent = 'Passwords match.';
              hint.className = 'ws-hint good';
            } else {
              hint.textContent = '';
              hint.className = 'ws-hint';
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
            sub.textContent = 'Opening dashboard...';
          });
        })();
      </script>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>