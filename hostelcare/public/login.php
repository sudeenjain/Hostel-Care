<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($u = current_user()) {
  redirect(home_for($u));
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $error = login_attempt(post('identifier'), (string) ($_POST['password'] ?? ''));
  if ($error === null) {
    redirect(home_for(current_user()));
  }
}
page_header('Sign in', null);
?>
<style>
  .lg {
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

  .lg * {
    box-sizing: border-box;
  }

  .lg-wrap {
    position: relative;
    margin-inline: calc(50% - 50vw);
    padding: 64px 20px 90px;
    overflow: hidden;
    min-height: 70vh;
    background: linear-gradient(180deg, #d6eaff 0%, #eaf4ff 55%, #fff 100%);
    display: grid;
    align-items: center;
  }

  .lg-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(110px);
    pointer-events: none;
  }

  .lg-glow.g1 {
    width: 460px;
    height: 460px;
    left: -120px;
    top: -140px;
    background: #fff;
    opacity: .8;
  }

  .lg-glow.g2 {
    width: 520px;
    height: 520px;
    right: -120px;
    top: 160px;
    background: #7fb8ff;
    opacity: .4;
  }

  .lg-grid {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 440px);
    gap: 64px;
    max-width: 1020px;
    margin: 0 auto;
    align-items: center;
  }

  .lg-intro h1 {
    margin: 0 0 14px;
    font-size: clamp(2rem, 4.4vw, 3.2rem);
    line-height: 1.08;
    font-weight: 800;
    letter-spacing: -.02em;
  }

  .lg-intro h1 span {
    color: var(--blue);
  }

  .lg-intro>p {
    margin: 0 0 26px;
    max-width: 420px;
    color: #3d5573;
    font-size: 1.05rem;
    line-height: 1.65;
  }

  .lg-points {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 12px;
  }

  .lg-points li {
    display: flex;
    gap: 12px;
    align-items: center;
    color: var(--muted);
    font-size: .95rem;
  }

  .lg-points i {
    flex: 0 0 28px;
    height: 28px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #fff;
    border: 1px solid var(--sky2);
    color: var(--blue);
    font-style: normal;
    font-weight: 800;
    font-size: .8rem;
  }

  .lg-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 26px;
    padding: 36px 34px;
    box-shadow: 0 18px 50px rgba(47, 128, 237, .15);
  }

  .lg-logo {
    text-align: center;
    margin-bottom: 18px;
  }

  .lg-logo img {
    height: 44px;
    width: auto;
    object-fit: contain;
  }

  .lg-card h2 {
    margin: 0 0 4px;
    text-align: center;
    font-size: 1.6rem;
    font-weight: 800;
  }

  .lg-sub {
    margin: 0 0 22px;
    text-align: center;
    color: var(--muted);
    font-size: .93rem;
  }

  .lg form {
    display: grid;
    gap: 16px;
    margin: 0;
  }

  .lg label {
    display: grid;
    gap: 7px;
    font-size: .86rem;
    font-weight: 600;
  }

  .lg input {
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

  .lg input:focus {
    background: #fff;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .15);
  }

  .lg input:user-invalid {
    border-color: var(--err);
  }

  .lg .pw {
    position: relative;
    display: block;
  }

  .lg .pw input {
    padding-right: 66px;
  }

  .lg .pw button {
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

  .lg .pw button:hover {
    background: var(--sky);
  }

  .lg-btn {
    width: 100%;
    margin-top: 4px;
    padding: 15px 24px;
    border: 0;
    border-radius: 14px;
    cursor: pointer;
    font: inherit;
    font-size: 1rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
    transition: transform .2s, opacity .2s;
  }

  .lg-btn:hover {
    transform: translateY(-2px);
  }

  .lg-btn:disabled {
    opacity: .65;
    cursor: wait;
    transform: none;
  }

  .lg-err {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    padding: 12px 14px;
    border-radius: 12px;
    background: #fff1f1;
    border: 1px solid #f5c6c6;
    color: #a32b2b;
    font-size: .9rem;
    line-height: 1.45;
  }

  .lg-err::before {
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

  .lg-alt {
    display: grid;
    gap: 10px;
    margin-top: 22px;
  }

  .lg-alt div {
    padding: 14px 16px;
    border-radius: 14px;
    background: var(--sky);
    font-size: .88rem;
    line-height: 1.5;
    color: #3d5573;
  }

  .lg-alt strong {
    display: block;
    color: var(--ink);
    font-size: .9rem;
  }

  .lg-alt a {
    color: var(--blue-d);
    font-weight: 700;
    text-decoration: none;
  }

  .lg-alt a:hover {
    text-decoration: underline;
  }

  @media (max-width:880px) {
    .lg-grid {
      grid-template-columns: 1fr;
      gap: 30px;
    }

    .lg-intro {
      display: none;
    }

    .lg-card {
      max-width: 460px;
      margin: 0 auto;
      width: 100%;
    }
  }

  @media (max-width:520px) {
    .lg-card {
      padding: 28px 20px;
    }
  }
</style>

<div class="lg">
  <div class="lg-wrap">
    <span class="lg-glow g1"></span><span class="lg-glow g2"></span>
    <div class="lg-grid">

      <aside class="lg-intro">
        <h1>Hostel complaints, <span>tracked from report to fix</span></h1>
        <p>Sign in to follow your complaints, manage your hostel and keep every update in one place.</p>
        <ul class="lg-points">
          <li><i>QR</i>Students file in seconds from a QR code</li>
          <li><i>SLA</i>Automatic deadlines and overdue tracking</li>
          <li><i>1:1</i>Every hostel's data stays separate</li>
        </ul>
      </aside>

      <div class="lg-card">
        <div class="lg-logo">
          <img src="<?= e(url('assets/img/logo.png')) ?>" alt="<?= e(cfg('app.name')) ?>">
        </div>
        <h2>Sign in</h2>
        <p class="lg-sub">Welcome back. Enter your details to continue.</p>

        <?php if ($error): ?><div class="lg-err flash flash-err" role="alert"><?= e($error) ?></div><?php endif; ?>

        <form method="post" id="lgForm">
          <?= csrf_field() ?>
          <label>Email<input type="email" name="identifier" required autofocus autocomplete="username" placeholder="you@example.com" value="<?= e(post('identifier')) ?>"></label>
          <label>Password
            <span class="pw"><input type="password" name="password" id="lgPw" required autocomplete="current-password"><button type="button" id="lgToggle">Show</button></span>
          </label>
          <button class="lg-btn" type="submit" id="lgBtn">Sign in</button>
        </form>

        <div class="lg-alt">
          <div><strong>Student?</strong>Scan your hostel's QR code to sign in with your hostel ID or to create your account.</div>
          <div><strong>Run a hostel?</strong><a href="<?= e(url('hostel/register.php')) ?>">Register it here</a>.</div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  (function() {
    var pw = document.getElementById('lgPw'),
      t = document.getElementById('lgToggle'),
      f = document.getElementById('lgForm'),
      b = document.getElementById('lgBtn');
    t.addEventListener('click', function() {
      var s = pw.type === 'password';
      pw.type = s ? 'text' : 'password';
      t.textContent = s ? 'Hide' : 'Show';
    });
    f.addEventListener('submit', function(e) {
      if (!f.checkValidity()) {
        e.preventDefault();
        f.reportValidity();
        return;
      }
      b.disabled = true;
      b.textContent = 'Signing in...';
    });
  })();
</script>
<?php page_footer(); ?>