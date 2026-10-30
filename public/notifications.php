<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  mark_all_read((int) $user['id']);
  redirect('notifications.php');
}
$items = list_notifications((int) $user['id']);
$target = $user['role'] === 'student' ? 'student/view.php' : 'warden/view.php';
$unread = 0;
foreach ($items as $n) {
  if (!$n['is_read']) {
    $unread++;
  }
}
page_header('Notifications', $user);
?>
<style>
  .nf {
    --blue: #2f80ed;
    --blue-d: #1b64c9;
    --sky: #eaf4ff;
    --sky2: #d6eaff;
    --ink: #0f2742;
    --muted: #5b6f87;
    --line: #d9e6f5;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    max-width: 820px;
    margin: 0 auto;
  }

  .nf * {
    box-sizing: border-box;
  }

  .nf-head {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
    padding: 28px 32px;
    margin-bottom: 22px;
    border-radius: 26px;
    background: linear-gradient(135deg, #d6eaff, #eaf4ff 60%, #fff);
    border: 1px solid var(--line);
  }

  .nf-head::before {
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

  .nf-head>* {
    position: relative;
  }

  .nf-head h1 {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin: 0;
    font-size: clamp(1.6rem, 3.2vw, 2.2rem);
    font-weight: 800;
    letter-spacing: -.02em;
  }

  .nf-count {
    padding: 5px 13px;
    border-radius: 999px;
    background: var(--blue);
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: 0;
  }

  .nf-head form {
    margin: 0;
  }

  .nf-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 22px;
    border-radius: 14px;
    border: 1.5px solid var(--sky2);
    cursor: pointer;
    font: inherit;
    font-size: .92rem;
    font-weight: 700;
    color: var(--blue-d);
    background: #fff;
    transition: background .2s, transform .2s;
  }

  .nf-btn:hover {
    background: var(--sky);
    transform: translateY(-2px);
  }

  .nf-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 22px;
    box-shadow: 0 12px 36px rgba(47, 128, 237, .10);
    overflow: hidden;
  }

  .nf-empty {
    padding: 36px 30px;
    text-align: center;
    color: var(--muted);
  }

  .nf-list {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .nf-list li {
    position: relative;
    border-bottom: 1px solid #edf3fa;
  }

  .nf-list li:last-child {
    border-bottom: 0;
  }

  .nf-row {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    padding: 18px 22px 18px 26px;
    color: inherit;
    text-decoration: none;
    transition: background .15s;
  }

  a.nf-row:hover {
    background: #f7fbff;
  }

  .nf-dot {
    flex: 0 0 10px;
    width: 10px;
    height: 10px;
    margin-top: 7px;
    border-radius: 50%;
    background: #cbd9ea;
  }

  .nf-body {
    flex: 1;
    min-width: 0;
  }

  .nf-msg {
    display: block;
    font-size: .95rem;
    line-height: 1.5;
    color: #26405f;
    overflow-wrap: anywhere;
  }

  .nf-time {
    display: block;
    margin-top: 4px;
    color: var(--muted);
    font-size: .8rem;
  }

  .nf-go {
    flex: 0 0 auto;
    align-self: center;
    color: var(--blue);
    font-weight: 800;
    opacity: 0;
    transform: translateX(-4px);
    transition: all .2s;
  }

  a.nf-row:hover .nf-go {
    opacity: 1;
    transform: none;
  }

  .nf-list li.unread {
    background: linear-gradient(90deg, #f1f7ff, #fff 70%);
  }

  .nf-list li.unread::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--blue);
  }

  .nf-list li.unread .nf-dot {
    background: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .18);
  }

  .nf-list li.unread .nf-msg {
    font-weight: 700;
    color: var(--ink);
  }

  @media (max-width:520px) {
    .nf-head {
      padding: 22px 18px;
    }

    .nf-row {
      padding: 16px 14px 16px 18px;
    }

    .nf-btn {
      width: 100%;
    }

    .nf-head form {
      width: 100%;
    }
  }
</style>

<div class="nf">
  <div class="nf-head">
    <h1>Notifications<?php if ($unread): ?> <span class="nf-count"><?= (int) $unread ?> unread</span><?php endif; ?></h1>
    <?php if ($items && $unread): ?>
      <form method="post"><?= csrf_field() ?><button class="nf-btn" type="submit">Mark all as read</button></form>
    <?php endif; ?>
  </div>

  <div class="nf-card">
    <?php if (!$items): ?>
      <div class="nf-empty"><?= empty_state('No notifications yet. You will be told here when a complaint changes.') ?></div>
    <?php else: ?>
      <ul class="nf-list">
        <?php foreach ($items as $n): ?>
          <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
            <?php if ($n['complaint_id']): ?>
              <a class="nf-row" href="<?= e(url($target . '?id=' . (int) $n['complaint_id'])) ?>">
                <span class="nf-dot"></span>
                <span class="nf-body"><span class="nf-msg"><?= e($n['message']) ?></span><span class="nf-time"><?= e(ago($n['created_at'])) ?></span></span>
                <span class="nf-go" aria-hidden="true">&rarr;</span>
              </a>
            <?php else: ?>
              <div class="nf-row">
                <span class="nf-dot"></span>
                <span class="nf-body"><span class="nf-msg"><?= e($n['message']) ?></span><span class="nf-time"><?= e(ago($n['created_at'])) ?></span></span>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
<?php page_footer(); ?>