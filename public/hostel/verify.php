<?php
require __DIR__ . '/../../includes/bootstrap.php';

$token = get('t');
$h = hostel_find_by_token('verify', $token);
$state = 'invalid';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $state = hostel_verify_email(post('t')) ? 'done' : 'invalid';
} elseif ($h && $h['status'] === 'unverified') {
  $state = 'confirm';
}
page_header('Confirm email', null);
?>
<style>
  .vf {
    --blue: #2f80ed;
    --blue-d: #1b64c9;
    --sky: #eaf4ff;
    --sky2: #d6eaff;
    --ink: #0f2742;
    --muted: #5b6f87;
    --line: #d9e6f5;
    --err: #d64545;
    --ok: #1f9d57;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
  }

  .vf * {
    box-sizing: border-box;
  }

  .vf-wrap {
    position: relative;
    margin-inline: calc(50% - 50vw);
    padding: 72px 20px 100px;
    overflow: hidden;
    background: linear-gradient(180deg, #d6eaff 0%, #eaf4ff 55%, #fff 100%);
  }

  .vf-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(110px);
    pointer-events: none;
  }

  .vf-glow.g1 {
    width: 460px;
    height: 460px;
    left: -120px;
    top: -140px;
    background: #fff;
    opacity: .8;
  }

  .vf-glow.g2 {
    width: 520px;
    height: 520px;
    right: -120px;
    top: 140px;
    background: #7fb8ff;
    opacity: .4;
  }

  .vf-card {
    position: relative;
    z-index: 1;
    max-width: 560px;
    margin: 0 auto;
    padding: 44px 36px;
    text-align: center;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 28px;
    box-shadow: 0 18px 50px rgba(47, 128, 237, .15);
  }

  .vf-ico {
    width: 72px;
    height: 72px;
    margin: 0 auto 20px;
    display: grid;
    place-items: center;
    border-radius: 20px;
    background: var(--sky);
    color: var(--blue);
    font-size: 1.6rem;
    font-weight: 800;
  }

  .vf-ico.ok {
    background: #e8f7ee;
    color: var(--ok);
    animation: vf-pop .5s ease-out;
  }

  .vf-ico.bad {
    background: #fff1f1;
    color: var(--err);
  }

  @keyframes vf-pop {
    0% {
      transform: scale(.6);
      opacity: 0;
    }

    70% {
      transform: scale(1.08);
    }

    100% {
      transform: scale(1);
      opacity: 1;
    }
  }

  .vf-card h1 {
    margin: 0 0 10px;
    font-size: clamp(1.6rem, 3.4vw, 2.1rem);
    font-weight: 800;
    letter-spacing: -.01em;
  }

  .vf-card>p {
    margin: 0 0 18px;
    color: #3d5573;
    line-height: 1.65;
  }

  .vf-card .muted {
    color: var(--muted);
    font-size: .92rem;
  }

  .vf-card a:not(.vf-btn) {
    color: var(--blue-d);
    font-weight: 700;
  }

  .vf-details {
    margin: 6px 0 24px;
    text-align: left;
    background: #f7fbff;
    border: 1px solid var(--line);
    border-radius: 16px;
    overflow: hidden;
  }

  .vf-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--line);
  }

  .vf-row:last-child {
    border-bottom: 0;
  }

  .vf-row span {
    color: var(--muted);
    font-size: .82rem;
    font-weight: 600;
    letter-spacing: .04em;
    text-transform: uppercase;
  }

  .vf-row strong {
    text-align: right;
    font-size: .98rem;
  }

  .vf-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
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

  .vf-btn:hover {
    transform: translateY(-2px);
  }

  .vf-btn:disabled {
    opacity: .65;
    cursor: wait;
    transform: none;
  }

  .vf-btn.light {
    color: var(--blue-d);
    background: #fff;
    border: 1.5px solid var(--sky2);
    box-shadow: none;
  }

  .vf-btn.light:hover {
    background: var(--sky);
  }

  .vf-safe {
    margin: 16px 0 0;
    font-size: .84rem;
    color: var(--muted);
    line-height: 1.5;
  }

  .vf-track {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin: 6px 0 22px;
    font-size: .8rem;
    font-weight: 600;
    color: var(--muted);
    flex-wrap: wrap;
  }

  .vf-track b {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 12px;
    border-radius: 999px;
    background: var(--sky);
    font-weight: 600;
  }

  .vf-track b i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #b8cbe3;
  }

  .vf-track b.done {
    color: var(--ok);
    background: #e8f7ee;
  }

  .vf-track b.done i {
    background: var(--ok);
  }

  .vf-track b.now {
    color: var(--blue-d);
  }

  .vf-track b.now i {
    background: var(--blue);
    animation: vf-blink 1.4s infinite;
  }

  @keyframes vf-blink {
    50% {
      opacity: .3;
    }
  }

  .vf-track em {
    width: 18px;
    height: 2px;
    background: var(--line);
  }

  @media (max-width:520px) {
    .vf-card {
      padding: 32px 20px;
    }

    .vf-row {
      flex-direction: column;
      gap: 2px;
    }

    .vf-row strong {
      text-align: left;
    }
  }

  @media (prefers-reduced-motion:reduce) {

    .vf-ico.ok,
    .vf-track b.now i {
      animation: none;
    }
  }
</style>

<div class="vf">
  <div class="vf-wrap">
    <span class="vf-glow g1"></span><span class="vf-glow g2"></span>
    <div class="vf-card">

      <?php if ($state === 'confirm'): ?>
        <div class="vf-ico">@</div>
        <h1>Confirm your email</h1>
        <p>Please check these details, then confirm that this email address is yours.</p>
        <div class="vf-details">
          <div class="vf-row"><span>Hostel</span><strong><?= e($h['name']) ?></strong></div>
          <div class="vf-row"><span>City</span><strong><?= e($h['city']) ?></strong></div>
          <div class="vf-row"><span>Registering as</span><strong><?= e($h['owner_name']) ?></strong></div>
        </div>
        <form method="post" id="vfForm"><?= csrf_field() ?><input type="hidden" name="t" value="<?= e($token) ?>">
          <button class="vf-btn" type="submit" id="vfBtn">Yes, this is my email</button>
        </form>
        <p class="vf-safe">Did not request this? You can safely ignore this page and close it. Nothing happens unless you confirm.</p>
        <script>
          (function() {
            var f = document.getElementById('vfForm'),
              b = document.getElementById('vfBtn');
            f.addEventListener('submit', function() {
              b.disabled = true;
              b.textContent = 'Confirming...';
            });
          })();
        </script>

      <?php elseif ($state === 'done'): ?>
        <div class="vf-ico ok">&#10003;</div>
        <h1>Request sent</h1>
        <p>Thank you. Your request has gone to the HostelCare administrator.</p>
        <div class="vf-track" aria-label="Progress">
          <b class="done"><i></i>Submitted</b><em></em>
          <b class="done"><i></i>Email verified</b><em></em>
          <b class="now"><i></i>Admin review</b>
        </div>
        <p class="muted">You will get an email when it is approved or if it is not. This usually does not take long.</p>
        <a class="vf-btn light" href="<?= e(url('')) ?>">Return to home</a>

      <?php else: ?>
        <div class="vf-ico bad">!</div>
        <h1>Link not valid</h1>
        <p class="muted">This confirmation link has expired or was already used. If you did not finish registering, you can start again.</p>
        <a class="vf-btn" href="<?= e(url('hostel/register.php')) ?>">Submit the request again</a>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php page_footer(); ?>