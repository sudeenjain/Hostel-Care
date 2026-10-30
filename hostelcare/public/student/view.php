<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('student');
auto_close_resolved();

$id = (int) get('id');
$c = get_complaint($id);
if (!$c) {
  not_found();
}
if (!can_view_complaint($user, $c)) {
  forbidden('This complaint belongs to another student.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = post('action');
  $err = null;
  if ($action === 'comment') {
    $err = add_note($user, $id, post('note'), false);
    $okMsg = 'Your message was added.';
  } elseif ($action === 'close') {
    $err = student_close($user, $id, (int) post('rating'), post('feedback'));
    $okMsg = 'Thank you. The complaint is closed.';
  } elseif ($action === 'reopen') {
    $err = student_reopen($user, $id, post('reason'));
    $okMsg = 'The complaint was reopened and the warden has been told.';
  } else {
    $err = 'Unknown action.';
  }
  flash($err ? 'err' : 'ok', $err ?: $okMsg);
  redirect('student/view.php?id=' . $id);
}

$timeline = complaint_timeline($id, false);
$files = complaint_attachments($id);
$canReopen = in_array($c['status'], ['resolved', 'closed'], true)
  && $c['resolved_at'] && time() - strtotime($c['resolved_at']) <= (int) cfg('rules.reopen_days') * 86400;
page_header(ticket_no($id), $user);
?>
<style>
  .sv {
    --blue: #2f80ed;
    --blue-d: #1b64c9;
    --sky: #eaf4ff;
    --sky2: #d6eaff;
    --ink: #0f2742;
    --muted: #5b6f87;
    --line: #d9e6f5;
    --err: #d64545;
    --warn: #d97706;
    --ok: #1f9d57;
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--ink);
    max-width: 1100px;
    margin: 0 auto;
  }

  .sv * {
    box-sizing: border-box;
  }

  .sv-back {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    margin-bottom: 14px;
    color: var(--blue-d);
    font-weight: 700;
    font-size: .88rem;
    text-decoration: none;
  }

  .sv-back:hover {
    text-decoration: underline;
  }

  .sv-head {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    align-items: flex-start;
    justify-content: space-between;
    padding: 28px 32px;
    margin-bottom: 22px;
    border-radius: 26px;
    background: linear-gradient(135deg, #d6eaff, #eaf4ff 60%, #fff);
    border: 1px solid var(--line);
  }

  .sv-head::before {
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

  .sv-head>* {
    position: relative;
  }

  .sv-head h1 {
    margin: 0 0 12px;
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    font-weight: 800;
    letter-spacing: -.02em;
    line-height: 1.2;
  }

  .sv-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .sv-meta span {
    padding: 5px 12px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--sky2);
    color: var(--blue-d);
    font-weight: 600;
    font-size: .8rem;
  }

  .sv-status {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
  }

  .sv-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
    gap: 22px;
    align-items: start;
  }

  .sv-stack {
    display: grid;
    gap: 22px;
  }

  .sv-card {
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 22px;
    padding: 26px;
    box-shadow: 0 12px 36px rgba(47, 128, 237, .10);
  }

  .sv-card.accent {
    border-color: var(--blue);
    background: linear-gradient(180deg, #f4f9ff, #fff);
    box-shadow: 0 14px 40px rgba(47, 128, 237, .18);
  }

  .sv-card h2 {
    margin: 0 0 14px;
    font-size: 1.12rem;
    font-weight: 700;
  }

  .sv-card h3 {
    margin: 22px 0 12px;
    font-size: .78rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--blue-d);
  }

  .sv-card .sub {
    margin: -6px 0 16px;
    color: var(--muted);
    font-size: .9rem;
    line-height: 1.5;
  }

  .sv .pre {
    margin: 0 0 20px;
    padding: 16px 18px;
    background: #f7fbff;
    border: 1px solid var(--line);
    border-radius: 14px;
    white-space: pre-wrap;
    line-height: 1.65;
    color: #26405f;
  }

  .sv dl {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0 22px;
    margin: 0;
  }

  .sv dt {
    padding: 11px 0;
    border-top: 1px solid #edf3fa;
    color: var(--muted);
    font-size: .82rem;
    font-weight: 600;
    letter-spacing: .03em;
  }

  .sv dd {
    margin: 0;
    padding: 10px 0;
    border-top: 1px solid #edf3fa;
    font-size: .93rem;
    font-weight: 600;
    text-align: right;
  }

  .sv dt:first-of-type,
  .sv dt:first-of-type+dd {
    border-top: 0;
  }

  .sv .thumbs {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
  }

  .sv .thumbs a {
    display: block;
    overflow: hidden;
    border-radius: 14px;
    border: 1px solid var(--line);
    aspect-ratio: 1;
  }

  .sv .thumbs img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .4s;
  }

  .sv .thumbs a:hover img {
    transform: scale(1.08);
  }

  .sv .tag {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 999px;
    background: var(--sky);
    color: var(--blue-d);
    font-size: .72rem;
    font-weight: 700;
  }

  .sv .tag.late {
    background: #fff4e0;
    color: var(--warn);
  }

  /* forms */
  .sv form {
    display: grid;
    gap: 14px;
    margin: 0;
  }

  .sv label {
    display: grid;
    gap: 7px;
    font-size: .86rem;
    font-weight: 600;
  }

  .sv textarea {
    width: 100%;
    padding: 13px 14px;
    font: inherit;
    font-size: .95rem;
    color: var(--ink);
    background: #f7fbff;
    border: 1.5px solid var(--line);
    border-radius: 12px;
    outline: 0;
    resize: vertical;
    transition: border-color .2s, box-shadow .2s, background .2s;
  }

  .sv textarea::placeholder {
    color: #9db0c6;
  }

  .sv textarea:focus {
    background: #fff;
    border-color: var(--blue);
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .15);
  }

  .sv textarea:user-invalid {
    border-color: var(--err);
  }

  .sv fieldset.stars {
    margin: 0;
    padding: 0;
    border: 0;
    min-width: 0;
  }

  .sv fieldset.stars legend {
    margin-bottom: 10px;
    padding: 0;
    font-size: .86rem;
    font-weight: 600;
  }

  .sv fieldset.stars {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
  }

  .sv fieldset.stars legend {
    flex: 0 0 100%;
  }

  .sv fieldset.stars label {
    position: relative;
    display: block;
    cursor: pointer;
  }

  .sv fieldset.stars input {
    position: absolute;
    opacity: 0;
    inset: 0;
    margin: 0;
    cursor: pointer;
  }

  .sv fieldset.stars span {
    display: grid;
    place-items: center;
    width: 50px;
    height: 50px;
    border-radius: 14px;
    background: #fff;
    border: 1.5px solid var(--line);
    color: var(--muted);
    font-size: 1.05rem;
    font-weight: 800;
    transition: all .2s;
  }

  .sv fieldset.stars label:hover span {
    border-color: var(--blue);
    color: var(--blue-d);
    transform: translateY(-2px);
  }

  .sv fieldset.stars input:checked+span {
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    border-color: var(--blue-d);
    color: #fff;
    box-shadow: 0 8px 20px rgba(47, 128, 237, .35);
  }

  .sv fieldset.stars input:focus-visible+span {
    box-shadow: 0 0 0 4px rgba(47, 128, 237, .25);
  }

  .sv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 14px 26px;
    border-radius: 14px;
    border: 1.5px solid transparent;
    cursor: pointer;
    font: inherit;
    font-size: .96rem;
    font-weight: 700;
    text-decoration: none;
    color: #fff;
    background: linear-gradient(180deg, #4a9bff, var(--blue-d));
    box-shadow: 0 10px 26px rgba(47, 128, 237, .35);
    transition: transform .2s, opacity .2s;
  }

  .sv-btn:hover {
    transform: translateY(-2px);
  }

  .sv-btn:disabled {
    opacity: .65;
    cursor: wait;
    transform: none;
  }

  .sv-btn.light {
    color: var(--blue-d);
    background: #fff;
    border-color: var(--sky2);
    box-shadow: none;
  }

  .sv-btn.light:hover {
    background: var(--sky);
  }

  /* timeline */
  .sv .timeline {
    list-style: none;
    margin: 0;
    padding: 0 0 0 6px;
  }

  .sv .timeline li {
    position: relative;
    padding: 0 0 22px 28px;
    border-left: 2px solid var(--sky2);
    margin-left: 6px;
  }

  .sv .timeline li:last-child {
    padding-bottom: 0;
    border-left-color: transparent;
  }

  .sv .timeline li::before {
    content: "";
    position: absolute;
    left: -8px;
    top: 3px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid var(--sky2);
  }

  .sv .timeline li.done::before {
    border-color: var(--blue);
    background: var(--blue);
  }

  .sv .timeline li.note::before {
    border-color: #9db0c6;
  }

  .sv .timeline b {
    display: block;
    font-size: .95rem;
  }

  .sv .timeline p {
    margin: 6px 0;
    padding: 10px 12px;
    background: #f7fbff;
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: .9rem;
    line-height: 1.55;
    color: #26405f;
    white-space: pre-wrap;
  }

  .sv .timeline small {
    color: var(--muted);
    font-size: .78rem;
  }

  @media (max-width:860px) {
    .sv-grid {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width:520px) {

    .sv-head,
    .sv-card {
      padding: 20px 18px;
    }

    .sv-btn {
      width: 100%;
    }

    .sv fieldset.stars span {
      width: 44px;
      height: 44px;
    }
  }
</style>

<div class="sv">
  <a class="sv-back" href="<?= e(url('student/dashboard.php')) ?>">&larr; All complaints</a>

  <div class="sv-head">
    <div>
      <h1><?= e($c['title']) ?></h1>
      <div class="sv-meta">
        <span><?= e(ticket_no($id)) ?></span>
        <span><?= e($c['category']) ?></span>
        <span><?= e($c['location']) ?></span>
      </div>
    </div>
    <div class="sv-status"><?= status_badge($c['status']) ?><?= is_overdue($c) ? ' <span class="tag late">Past target</span>' : '' ?></div>
  </div>

  <div class="sv-grid">
    <section class="sv-stack">
      <div class="sv-card">
        <h2>Details</h2>
        <p class="pre"><?= e($c['description']) ?></p>
        <dl>
          <dt>Priority</dt>
          <dd><?= priority_tag($c['priority']) ?></dd>
          <dt>Raised</dt>
          <dd><?= e(dt($c['created_at'])) ?></dd>
          <dt>Target date</dt>
          <dd><?= e(dt($c['due_at'])) ?></dd>
          <?php foreach (form_answers_decode($c['extra_json'] ?? null) as $ans): ?><dt><?= e($ans['label']) ?></dt>
            <dd><?= e($ans['value']) ?></dd><?php endforeach; ?>
          <dt>Handled by</dt>
          <dd><?= $c['staff_name'] ? e($c['staff_name'] . ' (' . $c['staff_trade'] . ')') : 'Not assigned yet' ?></dd>
          <?php if ($c['rating']): ?><dt>Your rating</dt>
            <dd><?= (int) $c['rating'] ?> / 5</dd><?php endif; ?>
        </dl>
        <?php if ($files): ?>
          <h3>Photos</h3>
          <div class="thumbs">
            <?php foreach ($files as $f): ?>
              <a href="<?= e(url('download.php?id=' . (int) $f['id'])) ?>" target="_blank" rel="noopener">
                <img src="<?= e(url('download.php?id=' . (int) $f['id'])) ?>" alt="<?= e($f['original_name']) ?>">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($c['status'] === 'resolved'): ?>
        <div class="sv-card accent">
          <h2>Is the problem fixed?</h2>
          <p class="sub">If you do nothing, this complaint closes by itself <?= (int) cfg('rules.auto_close_days') ?> days after it was resolved.</p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="close">
            <fieldset class="stars">
              <legend>Rate the resolution (1 = poor, 5 = excellent)</legend>
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <label><input type="radio" name="rating" value="<?= $i ?>" required><span><?= $i ?></span></label>
              <?php endfor; ?>
            </fieldset>
            <label>Comment (optional)<textarea name="feedback" rows="2" maxlength="1000"></textarea></label>
            <button class="sv-btn" type="submit">Confirm fixed and close</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($canReopen): ?>
        <div class="sv-card">
          <h2>Not fixed?</h2>
          <form method="post" data-confirm="Reopen this complaint?">
            <?= csrf_field() ?><input type="hidden" name="action" value="reopen">
            <label>What is still wrong?<textarea name="reason" rows="2" required minlength="5" maxlength="1000"></textarea></label>
            <button class="sv-btn light" type="submit">Reopen complaint</button>
          </form>
        </div>
      <?php endif; ?>
    </section>

    <aside class="sv-stack">
      <div class="sv-card">
        <h2>Progress</h2>
        <ul class="timeline">
          <?php foreach ($timeline as $t): ?>
            <li class="<?= $t['new_status'] ? 'done' : 'note' ?>">
              <b><?= $t['new_status'] && $t['new_status'] !== $t['old_status'] ? e(status_label($t['new_status'])) : ($t['new_status'] ? 'Update' : 'Message') ?></b>
              <?php if ($t['note']): ?><p><?= e($t['note']) ?></p><?php endif; ?>
              <small><?= e($t['user_name'] ?? 'System') ?> &middot; <?= e(dt($t['created_at'])) ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php if ($c['status'] !== 'closed'): ?>
        <div class="sv-card">
          <h2>Add a message</h2>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="comment">
            <textarea name="note" rows="3" required minlength="2" maxlength="2000" placeholder="Extra detail or a question for the warden"></textarea>
            <button class="sv-btn light" type="submit">Send</button>
          </form>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php page_footer(); ?>