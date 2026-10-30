<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/*  Who is signed in                                                   */
/*  Roles: admin = platform administrator (no hostel), warden = staff  */
/*  of one hostel, student = resident of one hostel.                   */
/* ------------------------------------------------------------------ */

/** Explains why a user may not use the system right now, or null if access is fine. */
function user_access_error(array $u): ?string
{
    if ($u['role'] === 'admin') {
        return null;
    }
    $status = $u['hostel_status'] ?? null;
    if ($status === 'suspended') {
        return 'This hostel has been suspended by the platform administrator.';
    }
    if ($u['role'] === 'warden' && !in_array($status, ['approved', 'active'], true)) {
        return 'This hostel is not active.';
    }
    if ($u['role'] === 'student' && $status !== 'active') {
        return 'This hostel is not accepting complaints yet.';
    }
    return null;
}

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    try {
        $row = q_one(
            'SELECT u.*, h.status AS hostel_status, h.name AS hostel_name
             FROM users u LEFT JOIN hostels h ON h.id = u.hostel_id WHERE u.id = ? AND u.active = 1',
            [$id]
        );
    } catch (Throwable $e) {
        unset($_SESSION['user_id']);
        return null;
    }
    if (!$row || user_access_error($row) !== null) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $row;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        forbidden();
    }
    return $user;
}

function is_staff_role(string $role): bool
{
    return $role === 'warden';
}

function home_for(array $user): string
{
    if ($user['role'] === 'admin') {
        return 'admin/dashboard.php';
    }
    if ($user['role'] === 'warden') {
        return ($user['hostel_status'] ?? '') === 'active' ? 'warden/queue.php' : 'warden/setup.php';
    }
    return 'student/dashboard.php';
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/* ------------------------------------------------------------------ */
/*  Sign in                                                            */
/* ------------------------------------------------------------------ */

function login_is_blocked(string $key): bool
{
    $since = date('Y-m-d H:i:s', time() - 60 * (int) cfg('rules.login_window_min'));
    $count = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at >= ?', [$key, $since]);
    return $count >= (int) cfg('rules.login_max_fail');
}

/**
 * Sign in with an email address (any role), or with a hostel login ID when $hostelId is given
 * (the student page reached through a hostel's QR code). Returns an error message or null.
 */
function login_attempt(string $identifier, string $password, ?int $hostelId = null): ?string
{
    $identifier = trim($identifier);
    if ($identifier === '') {
        return 'Enter your login ID or email.';
    }
    $byEmail = str_contains($identifier, '@');
    if (!$byEmail && $hostelId === null) {
        return 'Sign in with your email address. Students can also scan their hostel\'s QR code to sign in with a hostel ID.';
    }
    $key = ($byEmail ? strtolower($identifier) : strtoupper($identifier) . '@' . $hostelId);
    if (login_is_blocked($key)) {
        return 'Too many failed attempts. Try again in ' . cfg('rules.login_window_min') . ' minutes.';
    }
    $sql = 'SELECT u.*, h.status AS hostel_status, h.name AS hostel_name FROM users u LEFT JOIN hostels h ON h.id = u.hostel_id WHERE u.active = 1 AND ';
    $user = $byEmail
        ? q_one($sql . 'u.email = ?', [strtolower($identifier)])
        : q_one($sql . 'u.hostel_id = ? AND u.login_id = ?', [$hostelId, strtoupper($identifier)]);

    $hash = $user['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
    $valid = password_verify($password, $hash) && $user !== null;
    if ($valid && $hostelId !== null && (int) $user['hostel_id'] !== $hostelId) {
        $valid = false;   // an account of another hostel looks exactly like a wrong password
    }
    if (!$valid) {
        q('INSERT INTO login_attempts (identifier, ip, created_at) VALUES (?, ?, ?)', [$key, client_ip(), now()]);
        return 'Login ID or password is incorrect.';
    }
    if ($err = user_access_error($user)) {
        return $err;
    }
    q('DELETE FROM login_attempts WHERE identifier = ?', [$key]);
    if (PHP_SAPI !== 'cli') {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = (int) $user['id'];
    return null;
}

function logout_user(): void
{
    $_SESSION = [];
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/* ------------------------------------------------------------------ */
/*  Creating accounts                                                  */
/* ------------------------------------------------------------------ */

/**
 * Creates an account. $d keys: hostel_id (null for platform admins), login_id, name, email, phone, sms_opt_in,
 * block, room, role, is_owner, password. Returns error messages; an empty list means success and $newId is set.
 */
function create_user(array $d, ?int &$newId = null): array
{
    $errors = [];
    $role = (string) ($d['role'] ?? 'student');
    $hostelId = isset($d['hostel_id']) && $d['hostel_id'] !== '' && $d['hostel_id'] !== null ? (int) $d['hostel_id'] : null;
    $name = trim((string) ($d['name'] ?? ''));
    $email = strtolower(trim((string) ($d['email'] ?? '')));
    $password = (string) ($d['password'] ?? '');
    $loginId = trim((string) ($d['login_id'] ?? ''));

    if (!in_array($role, ['student', 'warden', 'admin'], true)) {
        $errors[] = 'Invalid role.';
    }
    if ($role === 'admin' && $hostelId !== null) {
        $errors[] = 'A platform administrator does not belong to a hostel.';
    }
    if ($role !== 'admin' && ($hostelId === null || q_val('SELECT 1 FROM hostels WHERE id = ?', [$hostelId]) === null)) {
        $errors[] = 'Choose a valid hostel.';
    }
    if ($loginId === '' && $role !== 'student') {
        $loginId = $email;
    }
    if ($role === 'student') {
        $loginId = strtoupper($loginId);
        if (!preg_match('/^[A-Z0-9][A-Z0-9._\-\/]{2,39}$/', $loginId)) {
            $errors[] = 'Hostel ID must be 3 to 40 characters (letters, numbers, . _ - /).';
        }
    } elseif (!filter_var($loginId, FILTER_VALIDATE_EMAIL) && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-\/]{2,39}$/', $loginId)) {
        $errors[] = 'Login ID must be 3 to 40 characters.';
    }
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Enter the full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < (int) cfg('rules.min_password')) {
        $errors[] = 'Password must be at least ' . cfg('rules.min_password') . ' characters.';
    }
    if ($role === 'student' && (trim((string) ($d['block'] ?? '')) === '' || trim((string) ($d['room'] ?? '')) === '')) {
        $errors[] = 'Students must enter block and room.';
    }
    $phoneRaw = trim((string) ($d['phone'] ?? ''));
    $phone = null;
    if ($phoneRaw !== '') {
        $phone = sms_normalize_phone($phoneRaw);
        if ($phone === null) {
            $errors[] = 'Enter a valid mobile number, for example 9876543210.';
        }
    }
    $optIn = !empty($d['sms_opt_in']) ? 1 : 0;
    if ($optIn && $phoneRaw === '') {
        $errors[] = 'Enter a mobile number to receive SMS updates.';
    }
    if (!$errors) {
        $exists = $hostelId === null
            ? q_val('SELECT 1 FROM users WHERE hostel_id IS NULL AND login_id = ?', [$loginId])
            : q_val('SELECT 1 FROM users WHERE hostel_id = ? AND login_id = ?', [$hostelId, $loginId]);
        if ($exists !== null) {
            $errors[] = 'That ID is already registered in this hostel.';
        }
        if (q_val('SELECT 1 FROM users WHERE email = ?', [$email]) !== null) {
            $errors[] = 'That email is already registered.';
        }
    }
    if ($errors) {
        return $errors;
    }
    q(
        'INSERT INTO users (hostel_id, login_id, name, email, phone, sms_opt_in, block, room, role, is_owner, password_hash, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        [
            $hostelId, $loginId, $name, $email, $phone, $optIn,
            trim((string) ($d['block'] ?? '')) ?: null,
            trim((string) ($d['room'] ?? '')) ?: null,
            $role, !empty($d['is_owner']) ? 1 : 0, password_hash($password, PASSWORD_DEFAULT), now(),
        ]
    );
    $newId = (int) db()->lastInsertId();
    return [];
}
