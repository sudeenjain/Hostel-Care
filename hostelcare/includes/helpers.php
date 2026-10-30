<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return cfg('app.base_path', '') . '/' . ltrim($path, '/');
}

/**
 * Full public address for links in emails and QR codes. Uses only configuration or the address saved by the
 * installer, never the request's Host header, so a forged header cannot poison links in emails.
 */
function public_url(string $path = ''): string
{
    $base = (string) cfg('app.public_url', '');
    if ($base === '' && function_exists('setting')) {
        try {
            $base = setting('public_url', '');
        } catch (Throwable $e) {
            $base = '';
        }
    }
    if ($base === '') {
        $base = 'http://localhost:8000';
    }
    return rtrim($base, '/') . url($path);
}

/** Address of the current request, shown to an admin during install so they can confirm it. */
function detect_site_address(): string
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
}

/** A random secret for an emailed link. Returns [token to email, hash to store]. */
function new_token(): array
{
    $token = bin2hex(random_bytes(32));
    return [$token, hash('sha256', $token)];
}

function token_matches(?string $storedHash, ?string $expires, string $given): bool
{
    if (!$storedHash || $given === '' || !preg_match('/^[a-f0-9]{64}$/', $given)) {
        return false;
    }
    if ($expires && strtotime($expires) < time()) {
        return false;
    }
    return hash_equals($storedHash, hash('sha256', $given));
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function dt(?string $value): string
{
    return $value ? date('d M Y, g:i A', strtotime($value)) : '-';
}

function ago(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $diff = time() - strtotime($value);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' h ago';
    }
    return floor($diff / 86400) . ' d ago';
}

function hours_label(float $hours): string
{
    if ($hours < 1) {
        return round($hours * 60) . ' min';
    }
    if ($hours < 48) {
        return round($hours, 1) . ' h';
    }
    return round($hours / 24, 1) . ' d';
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function get(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    csrf_verify();
}

function ticket_no(int $id): string
{
    return 'HC-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function status_labels(): array
{
    return [
        'submitted'    => 'Submitted',
        'acknowledged' => 'Acknowledged',
        'assigned'     => 'Assigned',
        'in_progress'  => 'In progress',
        'resolved'     => 'Resolved',
        'closed'       => 'Closed',
        'reopened'     => 'Reopened',
    ];
}

function status_label(string $status): string
{
    return status_labels()[$status] ?? $status;
}

function status_class(string $status): string
{
    return match ($status) {
        'submitted', 'reopened'          => 'b-open',
        'acknowledged', 'assigned'       => 'b-assign',
        'in_progress'                    => 'b-prog',
        'resolved', 'closed'             => 'b-done',
        default                          => 'b-assign',
    };
}

function priority_labels(): array
{
    return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
}

function forbidden(string $message = 'You do not have access to this page.'): never
{
    http_response_code(403);
    page_header('Access denied');
    echo '<div class="card"><h2>Access denied</h2><p class="muted">' . e($message) . '</p></div>';
    page_footer();
    exit;
}

function not_found(): never
{
    http_response_code(404);
    page_header('Not found');
    echo '<div class="card"><h2>Not found</h2><p class="muted">That record does not exist.</p></div>';
    page_footer();
    exit;
}
