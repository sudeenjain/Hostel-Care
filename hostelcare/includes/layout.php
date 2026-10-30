<?php
declare(strict_types=1);

function nav_items(array $user): array
{
    return match ($user['role']) {
        'student' => [
            ['student/dashboard.php', 'My complaints'],
            ['student/new.php', 'New complaint'],
        ],
        'warden' => ($user['hostel_status'] ?? '') === 'active' ? [
            ['warden/queue.php', 'Queue'],
            ['warden/analytics.php', 'Analytics'],
            ['warden/categories.php', 'Categories'],
            ['warden/form.php', 'Complaint form'],
            ['warden/staff.php', 'Service providers'],
            ['warden/users.php', 'People'],
            ['warden/hostel.php', 'Hostel & QR'],
            ['warden/export.php', 'Export'],
        ] : [
            ['warden/setup.php', 'Setup'],
            ['warden/hostel.php', 'Hostel'],
            ['warden/categories.php', 'Categories'],
            ['warden/form.php', 'Complaint form'],
            ['warden/staff.php', 'Service providers'],
        ],
        'admin' => [
            ['admin/dashboard.php', 'Hostels'],
            ['admin/requests.php', 'Requests'],
            ['admin/sms.php', 'SMS'],
            ['admin/mail.php', 'Email'],
            ['admin/users.php', 'Administrators'],
        ],
        default => [],
    };
}

function page_header(string $title, ?array $user = null): void
{
    $user = $user ?? current_user();
    $script = ltrim(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $base = ltrim((string) cfg('app.base_path', ''), '/');
    if ($base !== '' && str_starts_with($script, $base . '/')) {
        $script = substr($script, strlen($base) + 1);
    }
    $subtitle = $user ? ($user['role'] === 'admin' ? 'Platform administration' : (string) ($user['hostel_name'] ?? '')) : '';
    $pending = 0;
    if ($user && $user['role'] === 'admin') {
        try {
            $pending = (int) q_val("SELECT COUNT(*) FROM hostels WHERE status = 'pending'");
        } catch (Throwable) {
            $pending = 0;
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e(cfg('app.name')) ?></title>
<link rel="icon" type="image/png" href="<?= e(url('assets/img/logo.png')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= e($user ? url(home_for($user)) : url('')) ?>" aria-label="<?= e(cfg('app.name')) ?>">
    <span class="brand-badge">
      <img src="<?= e(url('assets/img/logo.png')) ?>" alt="<?= e(cfg('app.name')) ?>" class="brand-logo">
    </span>
  </a>
  <?php if ($subtitle !== ''): ?><span class="inst"><?= e($subtitle) ?></span><?php endif; ?>
  <?php if ($user): ?>
  <nav class="nav">
    <?php foreach (nav_items($user) as [$href, $label]): ?>
      <a href="<?= e(url($href)) ?>" class="<?= $script === $href ? 'on' : '' ?>"><?= e($label) ?><?= $href === 'admin/requests.php' && $pending ? ' <span class="count">' . $pending . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="who">
    <?php $unread = unread_count((int) $user['id']); ?>
    <a class="bell" href="<?= e(url('notifications.php')) ?>" title="Notifications">Alerts<?php if ($unread): ?> <span class="count"><?= $unread ?></span><?php endif; ?></a>
    <a class="bell" href="<?= e(url('profile.php')) ?>"><?= e($user['name']) ?> <small>(<?= e($user['role']) ?>)</small></a>
    <form method="post" action="<?= e(url('logout.php')) ?>" class="inline"><?= csrf_field() ?><button class="link" type="submit">Sign out</button></form>
  </div>
  <?php endif; ?>
</header>
<main class="wrap">
<?php foreach (flashes() as [$type, $msg]): ?>
  <div class="flash flash-<?= e($type) ?>" role="status"><?= e($msg) ?></div>
<?php endforeach;
}

function page_footer(): void
{
    ?>
</main>
<footer class="foot"><?= e(cfg('app.name')) ?> &middot; Hostel complaint management</footer>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
<?php
}

function status_badge(string $status): string
{
    return '<span class="badge ' . e(status_class($status)) . '">' . e(status_label($status)) . '</span>';
}

function priority_tag(string $priority): string
{
    return '<span class="p-' . e($priority) . '">' . e(priority_labels()[$priority] ?? $priority) . '</span>';
}

function hostel_badge(string $status): string
{
    $cls = ['active' => 'b-done', 'approved' => 'b-assign', 'pending' => 'b-prog', 'unverified' => 'b-prog', 'rejected' => 'b-open', 'suspended' => 'b-open'][$status] ?? 'b-assign';
    return '<span class="badge ' . $cls . '">' . e(hostel_status_label($status)) . '</span>';
}

function pager(int $total, int $page, array $query): string
{
    $size = (int) cfg('rules.page_size');
    $pages = (int) ceil($total / $size);
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $q = http_build_query(array_merge($query, ['page' => $i]));
        $html .= '<a class="' . ($i === $page ? 'on' : '') . '" href="?' . e($q) . '">' . $i . '</a>';
    }
    return $html . '</div>';
}

function empty_state(string $message): string
{
    return '<div class="empty">' . e($message) . '</div>';
}
