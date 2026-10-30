<?php
require __DIR__ . '/../../includes/bootstrap.php';

if ($u = current_user()) {
  redirect(home_for($u));
}

/* Remote hero image (hotlinked, nothing downloaded). Swap the URL here. */
$heroImg = 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=1920&q=70';

$errors = [];
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $input = [];
  foreach (['name', 'type', 'address', 'city', 'state', 'pincode', 'total_blocks', 'total_rooms', 'resident_count', 'hostel_phone', 'owner_name', 'owner_designation', 'owner_email', 'owner_phone', 'declare'] as $k) {
    $input[$k] = post($k);
  }
  $errors = hostel_request_create($input);
  $done = !$errors;
}
function old(string $k): string
{
  return e(post($k));
}
page_header('Register my hostel', null);
?>
<style>
  .hr {
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

  .hr * {
    box-sizing: border-box;
  }

  .hr-bleed {
    margin-inline: calc(50% - 50vw);
  }

  /* HERO */
  .hr-hero {
    position: relative;
    margin-inline: calc(50% - 50vw);
    padding: 84px 20px 120px;
    text-align: center;
    overflow: hidden;
    background: linear-gradient(180deg, #d6eaff, #eaf4ff) center/cover no-repeat;
  }

  .hr-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(214, 234, 255, .82), rgba(234, 244, 255, .93) 75%, #eaf4ff);
  }

  .hr-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(110px);
    pointer-events: none;
  }

  .hr-glow.g1 {
    width: 460px;
    height: 460px;
    left: -120px;
    top: -150px;
    background: #fff;
    opacity: .8;
  }

  .hr-glow.g2 {
    width: 520px;
    height: 520px;
    right: -120px;
    top: 60px;
    background: #7fb8ff;
    opacity: .4;
  }

  .hr-hero>div {
    position: relative;
    z-index: 1;
    max-width: 760px;
    margin: 0 auto;
  }

  .hr-badge {
    display: inline-flex;
    padding: 8px 16px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--sky2);
    color: var(--blue-d);
    box-shadow: 0 4px 16px rgba(47, 128, 237, .12);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
  }

  .hr-hero h1 {
    margin: 20px 0 14px;
    font-size: clamp(2.1rem, 5vw, 3.4rem);
    line-height: 1.08;
    font-weight: 800;
    letter-spacing: -.02em;
  }

  .hr-hero h1 span {
    color: var(--blue);
  }

  .hr-hero p {
    margin: 0 auto;
    max-width: 600px;
    color: #3d5573;
    font-size: 1.08rem;
    line-height: 1.65;
  }

  /* FORM SHELL */
  .hr-body {
    position: relative;
    z-index: 2;
    max-width: 860px;
    margin: -64px auto 80px;
    padding: 0 4px;
  }

  .hr-progress {
    position: sticky;
    top: 0;
    z-index: 5;
    margin: 0 0 18px;
    padding: 14px 18px;
    background: rgba(255, 255, 255, .92);
    backdrop-filter: blur(10px);
    border: 1px solid var(--line);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(47, 128, 237, .12);
    display: flex;
    align-items: center;
    gap: 14px;
  }

  .hr-progress span {
    font-size: .82rem;
    font-weight: 700;
    color: var(--blue-d);
    white-space: nowrap;
  }

  .hr-bar {
    flex: 1;
    height: 8px;
    border-radius: 99px;
    background: var(--sky2);
    overflow: hidden;
  }

  .hr-bar i {
    display: block;
    height: 100%;
    width: 0;
    border-radius: 99px;
    background: linear-gradient(90deg, #4a9bff, var(--blue-d));
    transition: width .3s;
  }

  .hr form {
    display: grid;
    gap: 22px;
  }

  .hr-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 24px;
    padding: 32px;
    box-shadow: 0 14px 40px rgba(47, 128, 237, .12);
  }

  .hr-head {
    display: flex;
    gap: 14px;
    align-items: center;
    margin-bottom: 6px;
  }

  .hr-head b {
    flex: 0 0 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: var(--sky);
    color: var(--blue);
    font-weight: 800;
  }

  .hr-head h2 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 700;
  }

  .hr-sub {
    margin: 0 0 22px 56px;
    color: var(--muted);
    font-size: .92rem;
  }

  .hr-fields {
    display: grid;
    gap: 16px;
  }

  .hr label {
    display: grid;
    gap: 7px;
    font-size: .86rem;
    font-weight: 600;
  }

  .hr label small {
    font-weight: 400;
    color: var(--muted);
  }

  .hr input:not([type=checkbox]),
  .hr select,
  .hr textarea {
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

  .hr textarea {
    resize: vertical;
    min-height: 80px;
  }

  .hr input::placeholder,
  .hr textarea::placeholder {
    color: #9db0c6;
  }

  .hr input:focus,
  .hr select:focus,
  .hr textarea:focus {
    background: #fff;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .15);
  }

  .hr input:user-invalid,
  .hr select:user-invalid,
  .hr textarea:user-invalid {
    border-color: var(--err);
  }

  .hr .row2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .hr .row3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
  }

  .hr .check {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 16px;
    background: var(--sky);
    border-radius: 14px;
    font-weight: 400;
    cursor: pointer;
  }

  .hr .check input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    accent-color: var(--blue);
    flex: 0 0 auto;
  }

  .hr .check span {
    font-size: .88rem;
    color: #3d5573;
    line-height: 1.5;
  }

  .hr-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
  }

  .hr-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 15px 30px;
    border-radius: 14px;
    border: 0;
    cursor: pointer;
    font: inherit;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    transition: transform .2s, box-shadow .2s, opacity .2s;
  }

  .hr-btn:hover {
    transform: translateY(-2px);
  }

  .hr-btn.primary {
    color: #fff;
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
  }

  .hr-btn.primary:disabled {
    opacity: .65;
    cursor: wait;
    transform: none;
  }

  .hr-btn.light {
    color: var(--blue-d);
    background: #fff;
    border: 1.5px solid var(--sky2);
  }

  .hr-btn.light:hover {
    background: var(--sky);
  }

  .hr-err {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    background: #fff1f1;
    border: 1px solid #f5c6c6;
    color: #a32b2b;
    font-size: .9rem;
    line-height: 1.45;
  }

  .hr-err::before {
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

  /* SUCCESS */
  .hr-done {
    max-width: 640px;
    margin: 60px auto 80px;
    padding: 44px 34px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 28px;
    box-shadow: 0 18px 50px rgba(47, 128, 237, .15);
  }

  .hr-done .ico {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    display: grid;
    place-items: center;
    border-radius: 20px;
    background: var(--sky);
    color: var(--blue);
    font-size: 1.4rem;
    font-weight: 800;
    animation: hr-pulse 2.4s ease-in-out infinite;
  }

  @keyframes hr-pulse {

    0%,
    100% {
      box-shadow: 0 0 0 0 rgba(47, 128, 237, .35);
    }

    50% {
      box-shadow: 0 0 0 16px rgba(47, 128, 237, 0);
    }
  }

  .hr-done h1 {
    margin: 0 0 8px;
    font-size: 2rem;
    font-weight: 800;
  }

  .hr-done>p {
    margin: 0;
    color: var(--muted);
  }

  .hr-pill {
    display: inline-block;
    margin: 18px 0 28px;
    padding: 10px 20px;
    border-radius: 999px;
    background: var(--sky);
    border: 1px solid var(--sky2);
    color: var(--blue-d);
    font-weight: 700;
    word-break: break-all;
  }

  .hr-steps {
    text-align: left;
    display: grid;
    gap: 12px;
    margin-bottom: 24px;
  }

  .hr-steps h4 {
    margin: 0 0 4px;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--blue-d);
  }

  .hr-step {
    display: flex;
    gap: 14px;
    padding: 14px 16px;
    background: #f7fbff;
    border: 1px solid var(--line);
    border-radius: 14px;
    font-size: .92rem;
    line-height: 1.5;
    color: #3d5573;
  }

  .hr-step b {
    flex: 0 0 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: var(--blue);
    color: #fff;
    font-size: .85rem;
  }

  .hr-step strong {
    color: var(--ink);
  }

  .hr-note {
    margin: 0 0 22px;
    font-size: .85rem;
    color: var(--muted);
  }

  .hr-done .hr-actions {
    justify-content: center;
  }

  @media (max-width:720px) {

    .hr .row2,
    .hr .row3 {
      grid-template-columns: 1fr;
    }

    .hr-card {
      padding: 24px 18px;
    }

    .hr-sub {
      margin-left: 0;
    }
  }

  @media (prefers-reduced-motion:reduce) {
    .hr-done .ico {
      animation: none;
    }

    .hr-bar i {
      transition: none;
    }
  }
</style>

<div class="hr">
  <?php if ($done): ?>

    <div class="hr-done">
      <div style="margin-bottom: 20px; display:flex; justify-content:center;">
        <span style="display:inline-flex; align-items:center; background:#ffffff; padding:6px 16px; border-radius:12px; box-shadow:0 4px 14px rgba(47,128,237,0.12); border:1px solid #d9e6f5;">
          <img src="<?= e(url('assets/img/logo.png')) ?>" alt="HostelCare" style="height:38px; width:auto; display:block;">
        </span>
      </div>
      <div class="ico">@</div>
      <h1>Check your email</h1>
      <p>We sent a secure confirmation link to verify your email address:</p>
      <div class="hr-pill"><?= old('owner_email') ?></div>

      <div class="hr-steps">
        <h4>What happens next</h4>
        <div class="hr-step"><b>1</b>
          <div><strong>Confirm your email:</strong> Open the email and click the confirmation link (valid for 48 hours).</div>
        </div>
        <div class="hr-step"><b>2</b>
          <div><strong>Platform admin review:</strong> Your verified request is sent to the HostelCare administrator to review.</div>
        </div>
        <div class="hr-step"><b>3</b>
          <div><strong>Warden setup &amp; QR code:</strong> Once approved, you will receive your setup link to open your dashboard and print your hostel's student QR poster.</div>
        </div>
      </div>

      <p class="hr-note">Didn't see it? Check your spam or promotions folder, or allow a couple of minutes for delivery.</p>
      <div class="hr-actions">
        <a class="hr-btn primary" href="https://mail.google.com" target="_blank" rel="noopener">Open Gmail</a>
        <a class="hr-btn light" href="<?= e(url('')) ?>">Return to home</a>
      </div>
    </div>

  <?php else: ?>

    <section class="hr-hero" style="background-image:url('<?= e($heroImg) ?>')">
      <span class="hr-glow g1"></span><span class="hr-glow g2"></span>
      <div>
        <div style="margin-bottom: 16px; display:flex; justify-content:center;">
          <span style="display:inline-flex; align-items:center; background:#ffffff; padding:6px 16px; border-radius:12px; box-shadow:0 4px 16px rgba(47,128,237,0.14); border:1px solid #d6eaff;">
            <img src="<?= e(url('assets/img/logo.png')) ?>" alt="HostelCare" style="height:38px; width:auto; display:block;">
          </span>
        </div>
        <span class="hr-badge">Warden &amp; Facility Onboarding</span>
        <h1>Register your <span>hostel</span></h1>
        <p>Bring your residence onto the unified HostelCare network. Set up custom complaint workflows, assign local staff, and generate student QR codes.</p>
      </div>
    </section>

    <div class="hr-body">
      <div class="hr-progress" aria-hidden="true"><span id="hrPct">0% complete</span>
        <div class="hr-bar"><i id="hrBar"></i></div>
      </div>

      <?php foreach ($errors as $err): ?><div class="hr-err flash flash-err" role="alert"><?= e($err) ?></div><?php endforeach; ?>

      <form method="post" id="hrForm" novalidate>
        <?= csrf_field() ?>

        <div class="hr-card">
          <div class="hr-head"><b>1</b>
            <h2>About the hostel</h2>
          </div>
          <p class="hr-sub">General building information and residential capacity.</p>
          <div class="hr-fields">
            <label>Hostel name
              <input name="name" required maxlength="120" placeholder="e.g. Srinivas Hall of Residence" value="<?= old('name') ?>">
            </label>

            <label>Type
              <select name="type" required>
                <option value="">Select hostel category...</option>
                <?php foreach (hostel_types() as $k => $v): ?>
                  <option value="<?= e($k) ?>" <?= post('type') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                <?php endforeach; ?>
              </select>
            </label>

            <label>Campus / street address
              <textarea name="address" rows="2" required maxlength="250" placeholder="e.g. 12 University Road, Block Campus"><?= old('address') ?></textarea>
            </label>

            <div class="row2">
              <label>City<input name="city" required maxlength="80" placeholder="e.g. Mangaluru" value="<?= old('city') ?>"></label>
              <label>State<input name="state" required maxlength="80" placeholder="e.g. Karnataka" value="<?= old('state') ?>"></label>
            </div>

            <div class="row2">
              <label>PIN code<input name="pincode" required maxlength="12" inputmode="numeric" placeholder="e.g. 575001" value="<?= old('pincode') ?>"></label>
              <label>Hostel landline / desk phone (optional)<input name="hostel_phone" inputmode="tel" placeholder="e.g. 0824123456" value="<?= old('hostel_phone') ?>"></label>
            </div>

            <div class="row3">
              <label>Total blocks<input type="number" name="total_blocks" min="1" max="200" required placeholder="e.g. 2" value="<?= old('total_blocks') ?>"></label>
              <label>Total rooms<input type="number" name="total_rooms" min="1" max="5000" required placeholder="e.g. 60" value="<?= old('total_rooms') ?>"></label>
              <label>Total residents<input type="number" name="resident_count" min="1" max="20000" required placeholder="e.g. 150" value="<?= old('resident_count') ?>"></label>
            </div>
          </div>
        </div>

        <div class="hr-card">
          <div class="hr-head"><b>2</b>
            <h2>About the warden / applicant</h2>
          </div>
          <p class="hr-sub">Account owner information for dashboard access and approval notifications.</p>
          <div class="hr-fields">
            <div class="row2">
              <label>Your full name<input name="owner_name" required maxlength="120" autocomplete="name" placeholder="e.g. Dr. Ramesh Rao" value="<?= old('owner_name') ?>"></label>
              <label>Your official designation<input name="owner_designation" required maxlength="60" placeholder="e.g. Chief Warden, Residential Lead" value="<?= old('owner_designation') ?>"></label>
            </div>
            <div class="row2">
              <label>Your email address<small>The approval link is delivered here.</small>
                <input type="email" name="owner_email" required autocomplete="email" placeholder="e.g. warden@hostel.edu" value="<?= old('owner_email') ?>"></label>
              <label>Your mobile phone number<input name="owner_phone" inputmode="tel" required autocomplete="tel" placeholder="e.g. 9876543210" value="<?= old('owner_phone') ?>"></label>
            </div>

            <label class="check">
              <input type="checkbox" name="declare" value="1" <?= post('declare') ? 'checked' : '' ?>>
              <span>I declare that I am authorized to register this hostel and the information submitted above is complete and accurate.</span>
            </label>
          </div>
        </div>

        <div class="hr-actions">
          <button class="hr-btn primary" type="submit" id="hrSubmit">Submit hostel request</button>
          <a class="hr-btn light" href="<?= e(url('')) ?>">Cancel</a>
        </div>
      </form>
    </div>

    <script>
      (function() {
        var f = document.getElementById('hrForm'),
          bar = document.getElementById('hrBar'),
          pct = document.getElementById('hrPct'),
          btn = document.getElementById('hrSubmit');
        var req = Array.prototype.slice.call(f.querySelectorAll('[required]'))
          .concat(Array.prototype.slice.call(f.querySelectorAll('input[name=declare]')));

        function progress() {
          var done = req.filter(function(el) {
            return el.type === 'checkbox' ? el.checked : el.value.trim() !== '';
          }).length;
          var p = Math.round(done / req.length * 100);
          bar.style.width = p + '%';
          pct.textContent = p + '% complete';
        }
        f.addEventListener('input', progress);
        f.addEventListener('change', progress);
        progress();
        f.addEventListener('submit', function(e) {
          if (!f.checkValidity()) {
            e.preventDefault();
            f.reportValidity();
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