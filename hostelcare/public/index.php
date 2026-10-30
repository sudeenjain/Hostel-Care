<?php
require __DIR__ . '/../includes/bootstrap.php';
if ($user = current_user()) {
  redirect(home_for($user));
}

/* ---- Remote media: swap URLs here only. Nothing is downloaded. ---- */
$media = [
  'hero_video'  => 'https://videos.pexels.com/video-files/3129671/3129671-uhd_2560_1440_30fps.mp4',
  'hero_poster' => 'https://images.unsplash.com/photo-1541339907198-e08756dedf3f?auto=format&fit=crop&w=1920&q=70',
  'warden_img'  => 'https://images.unsplash.com/photo-1555854877-bab0e564b8d5?auto=format&fit=crop&w=1000&q=70',
  'student_img' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1000&q=70',
];

page_header('Welcome', null);
?>
<style>
  .lp {
    --blue: #2f80ed;
    --blue-d: #1b64c9;
    --sky: #eaf4ff;
    --sky2: #d6eaff;
    --ink: #0f2742;
    --muted: #5b6f87;
    --line: #d9e6f5;
    color: var(--ink);
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  }

  .lp * {
    box-sizing: border-box;
  }

  .lp .bleed {
    margin-inline: calc(50% - 50vw);
  }

  /* ---------- HERO ---------- */
  .lp-hero {
    position: relative;
    margin-inline: calc(50% - 50vw);
    overflow: hidden;
    text-align: center;
    padding: 120px 24px 140px;
    background: linear-gradient(180deg, #d6eaff, #eaf4ff) center/cover no-repeat;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 26px;
  }

  .lp-hero video {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: 0;
  }

  .lp-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 1;
    background: linear-gradient(180deg, rgba(214, 234, 255, .80) 0%, rgba(234, 244, 255, .88) 70%, #eaf4ff 100%);
  }

  .lp-glow {
    position: absolute;
    z-index: 1;
    border-radius: 50%;
    filter: blur(110px);
    pointer-events: none;
  }

  .lp-glow.g1 {
    width: 520px;
    height: 520px;
    left: -120px;
    top: -160px;
    background: #fff;
    opacity: .75;
  }

  .lp-glow.g2 {
    width: 560px;
    height: 560px;
    right: -100px;
    top: 120px;
    background: #7fb8ff;
    opacity: .45;
  }

  .lp-hero>*:not(video):not(.lp-glow) {
    position: relative;
    z-index: 2;
  }

  .lp-brand-pill {
    display: inline-flex;
    align-items: center;
    background: #ffffff;
    padding: 8px 20px;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(47, 128, 237, 0.16);
    border: 1px solid var(--sky2);
    margin-bottom: 2px;
  }

  .lp-hero-logo {
    height: 46px;
    width: auto;
    display: block;
    object-fit: contain;
  }

  .lp-badge {
    display: inline-flex;
    padding: 9px 18px;
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

  .lp-hero h1 {
    margin: 0;
    max-width: 900px;
    font-size: clamp(2.3rem, 5.6vw, 4.25rem);
    line-height: 1.09;
    font-weight: 800;
    letter-spacing: -.02em;
    color: var(--ink);
  }

  .lp-hero p {
    margin: 0;
    max-width: 680px;
    font-size: clamp(1rem, 1.6vw, 1.2rem);
    line-height: 1.6;
    color: #3d5573;
  }

  .lp-cta {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 16px;
  }

  .lp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 15px 28px;
    border-radius: 14px;
    font-size: 15px;
    font-weight: 700;
    text-decoration: none;
    transition: transform .2s, box-shadow .2s, background .2s;
  }

  .lp-btn:hover {
    transform: translateY(-2px);
  }

  .lp-btn.primary {
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    color: #fff;
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
  }

  .lp-btn.light {
    background: #fff;
    color: var(--blue-d);
    border: 1.5px solid var(--sky2);
  }

  .lp-btn.light:hover {
    background: var(--sky);
  }

  /* ---------- STATS ---------- */
  .lp-stats-band {
    margin-inline: calc(50% - 50vw);
    background: var(--sky);
    padding: 40px 24px 20px;
    display: flex;
    justify-content: center;
  }

  .lp-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 240px));
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 22px;
    box-shadow: 0 18px 50px rgba(47, 128, 237, .15);
    overflow: hidden;
    max-width: 100%;
  }

  .lp-stat {
    padding: 26px 20px;
    text-align: center;
    border-right: 1px solid var(--line);
  }

  .lp-stat:last-child {
    border-right: 0;
  }

  .lp-stat b {
    display: block;
    font-size: 1.9rem;
    font-weight: 800;
    color: var(--blue);
    margin-bottom: 4px;
  }

  .lp-stat small {
    color: var(--muted);
    font-size: .88rem;
  }

  /* ---------- PORTALS ---------- */
  .lp-portals {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 32px;
    max-width: 1152px;
    margin: 70px auto;
  }

  .lp-portal {
    display: flex;
    flex-direction: column;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 10px 34px rgba(47, 128, 237, .10);
    transition: transform .3s, box-shadow .3s;
  }

  .lp-portal:hover {
    transform: translateY(-6px);
    box-shadow: 0 22px 55px rgba(47, 128, 237, .22);
  }

  .lp-portal-img {
    position: relative;
    height: 230px;
    overflow: hidden;
    background: linear-gradient(180deg, #9cc9ff, #d6eaff);
  }

  .lp-portal-img .bg {
    position: absolute;
    inset: 0;
    background: center/cover no-repeat;
    transition: transform .8s ease;
  }

  .lp-portal:hover .bg {
    transform: scale(1.08);
  }

  .lp-portal-img::after {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(47, 128, 237, .05), rgba(214, 234, 255, .55));
  }

  .lp-chip {
    position: absolute;
    z-index: 2;
    left: 18px;
    bottom: 18px;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(255, 255, 255, .92);
    color: var(--blue-d);
  }

  .lp-portal-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 28px;
    flex: 1;
  }

  .lp-portal h2 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--ink);
  }

  .lp-portal p {
    margin: 0 0 6px;
    color: var(--muted);
    font-size: .95rem;
    line-height: 1.6;
    flex: 1;
  }

  .lp-portal .lp-btn {
    width: 100%;
  }

  /* ---------- FEATURES ---------- */
  .lp-feat {
    margin-inline: calc(50% - 50vw);
    padding: 80px 24px 100px;
    background: linear-gradient(180deg, #fff, var(--sky));
  }

  .lp-feat h2 {
    margin: 0 0 10px;
    text-align: center;
    font-size: clamp(1.7rem, 3.4vw, 2.5rem);
    font-weight: 700;
    letter-spacing: -.01em;
  }

  .lp-feat .sub {
    margin: 0 0 44px;
    text-align: center;
    color: var(--muted);
    font-size: 1.05rem;
  }

  .lp-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    max-width: 1260px;
    margin: 0 auto;
  }

  .lp-feature {
    padding: 28px;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(47, 128, 237, .08);
    transition: transform .3s, border-color .3s, box-shadow .3s;
  }

  .lp-feature:hover {
    transform: translateY(-6px);
    border-color: var(--blue);
    box-shadow: 0 16px 40px rgba(47, 128, 237, .18);
  }

  .lp-ico {
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    margin-bottom: 16px;
    border-radius: 14px;
    background: var(--sky);
    color: var(--blue);
    font-weight: 700;
    font-size: 1rem;
  }

  .lp-feature h3 {
    margin: 0 0 10px;
    font-size: 1.12rem;
    font-weight: 700;
    line-height: 1.3;
  }

  .lp-feature p {
    margin: 0;
    color: var(--muted);
    font-size: .9rem;
    line-height: 1.6;
  }

  /* ---------- REVEAL & MOTION ---------- */
  .lp-reveal {
    opacity: 1;
    transform: none;
    transition: opacity .6s ease, transform .6s ease;
  }

  .lp-reveal.in {
    opacity: 1;
    transform: none;
  }

  @media (max-width:820px) {
    .lp-stats {
      grid-template-columns: repeat(2, 1fr);
    }

    .lp-stat:nth-child(2) {
      border-right: 0;
    }

    .lp-stat:nth-child(-n+2) {
      border-bottom: 1px solid var(--line);
    }
  }

  @media (prefers-reduced-motion:reduce) {
    .lp-hero video {
      display: none;
    }

    .lp-reveal {
      opacity: 1;
      transform: none;
      transition: none;
    }

    .lp-portal-img .bg {
      transition: none;
    }
  }
</style>

<div class="lp">

  <!-- HERO (live video background) -->
  <section class="lp-hero" style="background-image:url('<?= e($media['hero_poster']) ?>')">
    <video autoplay muted loop playsinline preload="metadata" poster="<?= e($media['hero_poster']) ?>" onerror="this.style.display='none'">
      <source src="<?= e($media['hero_video']) ?>" type="video/mp4">
    </video>
    <span class="lp-glow g1"></span>
    <span class="lp-glow g2"></span>

    <div class="lp-brand-pill">
      <img src="<?= e(url('assets/img/logo.png')) ?>" alt="HostelCare" class="lp-hero-logo">
    </div>

    <span class="lp-badge">Smart Campus Complaint Management</span>
    <h1>Hostel complaints,<br>tracked from report to fix</h1>
    <p>Every hostel gets its own dashboard, custom complaint form and QR code. Students scan, report, and follow the progress in real time.</p>
    <div class="lp-cta">
      <a class="lp-btn primary" href="<?= e(url('hostel/register.php')) ?>">Register my hostel</a>
      <a class="lp-btn light" href="<?= e(url('login.php')) ?>">Student sign in</a>
    </div>
  </section>

  <!-- STATS -->
  <div class="lp-stats-band">
    <div class="lp-stats lp-reveal">
      <div class="lp-stat"><b>QR</b><small>Scan &amp; file</small></div>
      <div class="lp-stat"><b>SLA</b><small>Auto deadlines</small></div>
      <div class="lp-stat"><b>1:1</b><small>Hostel isolation</small></div>
      <div class="lp-stat"><b>Live</b><small>Status timeline</small></div>
    </div>
  </div>

  <!-- PORTALS -->
  <div class="lp-portals">
    <article class="lp-portal lp-reveal">
      <div class="lp-portal-img">
        <div class="bg" style="background-image:url('<?= e($media['warden_img']) ?>')"></div>
        <span class="lp-chip">Administration &amp; Wardens</span>
      </div>
      <div class="lp-portal-body">
        <h2>I run a hostel</h2>
        <p>Wardens and hostel managers: ask to bring your hostel onto HostelCare. After approval you design your own complaint form, add your nearby service providers, and print a QR code for your students.</p>
        <a class="lp-btn primary" href="<?= e(url('hostel/register.php')) ?>">Register my hostel</a>
      </div>
    </article>

    <article class="lp-portal lp-reveal">
      <div class="lp-portal-img">
        <div class="bg" style="background-image:url('<?= e($media['student_img']) ?>')"></div>
        <span class="lp-chip">Hostel Residents</span>
      </div>
      <div class="lp-portal-body">
        <h2>I am a student</h2>
        <p>Scan the QR code displayed in your hostel. It opens your hostel's complaint page directly. If you already have an account you can sign in below.</p>
        <a class="lp-btn light" href="<?= e(url('login.php')) ?>">Sign in</a>
      </div>
    </article>
  </div>

  <!-- FEATURES -->
  <section class="lp-feat">
    <h2 class="lp-reveal">Built for wardens. Loved by students.</h2>
    <p class="sub lp-reveal">Everything needed to run a transparent complaint workflow.</p>
    <div class="lp-features">
      <div class="lp-feature lp-reveal">
        <div class="lp-ico">01</div>
        <h3>Automated SLA Deadlines</h3>
        <p>Target resolution hours assigned per complaint category, with automated overdue tracking.</p>
      </div>
      <div class="lp-feature lp-reveal">
        <div class="lp-ico">02</div>
        <h3>Instant QR Code Filing</h3>
        <p>Printable QR posters for notice boards. Students scan and file in seconds without app installs.</p>
      </div>
      <div class="lp-feature lp-reveal">
        <div class="lp-ico">03</div>
        <h3>Strict Multi-Tenancy</h3>
        <p>Complaints are isolated strictly by hostel. Confidential reports are masked for maximum privacy.</p>
      </div>
      <div class="lp-feature lp-reveal">
        <div class="lp-ico">04</div>
        <h3>Full Audit Timelines</h3>
        <p>Transparent work-order history with assigned technician updates and student ratings on completion.</p>
      </div>
    </div>
  </section>

</div>

<script>
  (function() {
    var els = document.querySelectorAll('.lp-reveal');
    if (!('IntersectionObserver' in window)) {
      els.forEach(function(e) {
        e.classList.add('in');
      });
      return;
    }
    var io = new IntersectionObserver(function(entries) {
      entries.forEach(function(en, i) {
        if (en.isIntersecting) {
          en.target.style.transitionDelay = (i * 80) + 'ms';
          en.target.classList.add('in');
          io.unobserve(en.target);
        }
      });
    }, {
      threshold: 0.15
    });
    els.forEach(function(e) {
      io.observe(e);
    });
  })();
</script>

<?php page_footer(); ?>