<?php
declare(strict_types=1);

/*
 * Hostel onboarding.
 *   unverified -> (warden confirms email) -> pending -> (platform admin accepts) -> approved
 *   approved -> (warden sets password, completes setup, publishes) -> active
 *   pending -> rejected.   active -> suspended (and back).
 * Every emailed link carries a random secret; only its SHA-256 hash is stored, links expire, and each is used once.
 */

const HOSTEL_VERIFY_HOURS = 48;
const HOSTEL_DECISION_DAYS = 14;
const HOSTEL_SETUP_DAYS = 7;

function hostel_types(): array
{
    return ['boys' => "Boys' hostel", 'girls' => "Girls' hostel", 'co_ed' => 'Co-ed hostel'];
}

function hostel_get(int $id): ?array
{
    return q_one('SELECT * FROM hostels WHERE id = ?', [$id]);
}

function hostel_status_label_map(): array
{
    return [
        'unverified' => 'Email not confirmed', 'pending' => 'Waiting for approval', 'approved' => 'Approved, setting up',
        'active' => 'Active', 'rejected' => 'Rejected', 'suspended' => 'Suspended',
    ];
}

function hostel_status_label(string $s): string
{
    return hostel_status_label_map()[$s] ?? $s;
}

function hostel_blocks_list(array $hostel): array
{
    $out = [];
    foreach (preg_split('/\R/', (string) $hostel['blocks']) as $b) {
        $b = trim($b);
        if ($b !== '' && !in_array($b, $out, true)) {
            $out[] = $b;
        }
    }
    return $out;
}

/** Cleans the text typed for blocks (one per line). Returns [normalised text, error|null]. */
function hostel_parse_blocks(string $raw): array
{
    $list = [];
    foreach (preg_split('/[\r\n,]+/', $raw) as $b) {
        $b = trim($b);
        if ($b === '') {
            continue;
        }
        if (mb_strlen($b) > 40) {
            return ['', 'Each block name must be 40 characters or fewer.'];
        }
        if (!in_array($b, $list, true)) {
            $list[] = $b;
        }
    }
    if (count($list) > 200) {
        return ['', 'That is too many blocks (maximum 200).'];
    }
    return [implode("\n", $list), null];
}

/* ------------------------------------------------------------------ */
/*  1. The warden's request                                            */
/* ------------------------------------------------------------------ */

function hostel_validate_request(array $d): array
{
    $e = [];
    $len = fn(string $k) => mb_strlen(trim((string) ($d[$k] ?? '')));
    if ($len('name') < 3 || $len('name') > 120) {
        $e[] = 'Enter the hostel name (3 to 120 characters).';
    }
    if (!isset(hostel_types()[(string) ($d['type'] ?? '')])) {
        $e[] = 'Choose the type of hostel.';
    }
    if ($len('address') < 5 || $len('address') > 250) {
        $e[] = 'Enter the hostel address.';
    }
    if ($len('city') < 2 || $len('city') > 80) {
        $e[] = 'Enter the city.';
    }
    if ($len('state') < 2 || $len('state') > 80) {
        $e[] = 'Enter the state.';
    }
    if (!preg_match('/^[A-Za-z0-9 \-]{4,12}$/', trim((string) ($d['pincode'] ?? '')))) {
        $e[] = 'Enter a valid PIN code.';
    }
    foreach (['total_blocks' => [1, 200, 'number of blocks'], 'total_rooms' => [1, 5000, 'number of rooms'], 'resident_count' => [1, 20000, 'number of residents']] as $k => [$lo, $hi, $label]) {
        $v = (string) ($d[$k] ?? '');
        if (!ctype_digit($v) || (int) $v < $lo || (int) $v > $hi) {
            $e[] = "Enter the $label ($lo to $hi).";
        }
    }
    if (trim((string) ($d['hostel_phone'] ?? '')) !== '' && sms_normalize_phone((string) $d['hostel_phone']) === null) {
        $e[] = 'The hostel phone number is not valid.';
    }
    if ($len('owner_name') < 2 || $len('owner_name') > 120) {
        $e[] = 'Enter your full name.';
    }
    if ($len('owner_designation') < 2 || $len('owner_designation') > 60) {
        $e[] = 'Enter your designation, for example Warden.';
    }
    if (!filter_var(strtolower(trim((string) ($d['owner_email'] ?? ''))), FILTER_VALIDATE_EMAIL)) {
        $e[] = 'Enter a valid email address. The approval link will be sent there.';
    }
    if (sms_normalize_phone((string) ($d['owner_phone'] ?? '')) === null) {
        $e[] = 'Enter your mobile number.';
    }
    if (empty($d['declare'])) {
        $e[] = 'Please confirm that you are authorised to register this hostel.';
    }
    return $e;
}

/** Saves a request and emails the confirmation link. Returns errors; empty means success. */
function hostel_request_create(array $d, ?int &$id = null): array
{
    $errors = hostel_validate_request($d);
    $email = strtolower(trim((string) ($d['owner_email'] ?? '')));
    if (!$errors) {
        if (q_val('SELECT 1 FROM users WHERE email = ?', [$email]) !== null) {
            $errors[] = 'That email already has an account. Sign in instead.';
        } elseif (q_val("SELECT 1 FROM hostels WHERE owner_email = ? AND status IN ('pending','approved','active','suspended')", [$email]) !== null) {
            $errors[] = 'A request from that email already exists.';
        } elseif ((int) q_val('SELECT COUNT(*) FROM hostels WHERE created_ip = ? AND created_at >= ?', [client_ip(), date('Y-m-d H:i:s', time() - 86400)]) >= 5) {
            $errors[] = 'Too many requests from your network today. Try again tomorrow.';
        } elseif ((int) q_val('SELECT COUNT(*) FROM mail_outbox WHERE to_email = ? AND created_at >= ?', [$email, date('Y-m-d H:i:s', time() - 86400)]) >= 3) {
            $errors[] = 'We already sent several emails to that address today. Check your inbox and spam folder.';
        }
    }
    if ($errors) {
        return $errors;
    }
    q("DELETE FROM hostels WHERE owner_email = ? AND status IN ('unverified','rejected')", [$email]);
    [$token, $hash] = new_token();
    $t = fn(string $k) => trim((string) ($d[$k] ?? ''));
    $phone = $t('hostel_phone') === '' ? null : sms_normalize_phone($t('hostel_phone'));
    q(
        "INSERT INTO hostels (name, type, address, city, state, pincode, total_blocks, total_rooms, resident_count, hostel_phone,
                              owner_name, owner_email, owner_phone, owner_designation, status, verify_hash, verify_expires, blocks, created_ip, created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'unverified', ?, ?, '', ?, ?)",
        [
            $t('name'), $t('type'), $t('address'), $t('city'), $t('state'), strtoupper($t('pincode')),
            (int) $d['total_blocks'], (int) $d['total_rooms'], (int) $d['resident_count'], $phone,
            $t('owner_name'), $email, sms_normalize_phone((string) $d['owner_phone']), $t('owner_designation'),
            $hash, date('Y-m-d H:i:s', time() + HOSTEL_VERIFY_HOURS * 3600), client_ip(), now(),
        ]
    );
    $id = (int) db()->lastInsertId();
    $link = public_url('hostel/verify.php?t=' . $token);
    mail_queue(
        $email,
        'Confirm your email to register ' . $t('name') . ' on HostelCare',
        "Hello {$t('owner_name')},\n\nSomeone (hopefully you) asked to register \"{$t('name')}\" on HostelCare.\n\n"
        . "Please confirm that this email address is yours:\n$link\n\nThe link works for " . HOSTEL_VERIFY_HOURS . " hours.\n"
        . "After you confirm, your request goes to the HostelCare administrator for approval.\n\nIf you did not ask for this, ignore this email.",
        mail_html('Confirm your email', "Hello " . e($t('owner_name')) . ",<br><br>Someone (hopefully you) asked to register <strong>" . e($t('name')) . "</strong> on HostelCare.", 'Confirm my email', $link, 'The link works for ' . HOSTEL_VERIFY_HOURS . ' hours. After you confirm, your request goes to the HostelCare administrator for approval. If you did not ask for this, ignore this email.')
    );
    return [];
}

function mail_html(string $title, string $bodyHtml, string $button, string $link, string $footer): string
{
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#1b2236">'
        . '<h2 style="color:#4338ca">' . e($title) . '</h2><p style="line-height:1.5">' . $bodyHtml . '</p>'
        . '<p><a href="' . e($link) . '" style="background:#4338ca;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block">' . e($button) . '</a></p>'
        . '<p style="color:#667085;font-size:13px">' . e($footer) . '</p></div>';
}

/* ------------------------------------------------------------------ */
/*  2. The warden confirms the email, administrators are told          */
/* ------------------------------------------------------------------ */

function hostel_find_by_token(string $column, string $token): ?array
{
    if (!in_array($column, ['verify', 'decision', 'setup'], true) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $h = q_one("SELECT * FROM hostels WHERE {$column}_hash = ?", [hash('sha256', $token)]);
    if (!$h || !token_matches($h[$column . '_hash'], $h[$column . '_expires'], $token)) {
        return null;
    }
    return $h;
}

function hostel_verify_email(string $token): ?array
{
    $h = hostel_find_by_token('verify', $token);
    if (!$h || $h['status'] !== 'unverified') {
        return null;
    }
    [$dToken, $dHash] = new_token();
    q(
        "UPDATE hostels SET status = 'pending', email_verified_at = ?, verify_hash = NULL, verify_expires = NULL,
                decision_hash = ?, decision_expires = ? WHERE id = ? AND status = 'unverified'",
        [now(), $dHash, date('Y-m-d H:i:s', time() + HOSTEL_DECISION_DAYS * 86400), $h['id']]
    );
    $h = hostel_get((int) $h['id']);
    hostel_email_admins($h, $dToken);
    return $h;
}

function hostel_summary_text(array $h): string
{
    return "Hostel:       {$h['name']} (" . (hostel_types()[$h['type']] ?? $h['type']) . ")\n"
        . "Address:      {$h['address']}, {$h['city']}, {$h['state']} {$h['pincode']}\n"
        . "Blocks/rooms: {$h['total_blocks']} blocks, {$h['total_rooms']} rooms, about {$h['resident_count']} residents\n"
        . 'Hostel phone: ' . ($h['hostel_phone'] ?: '-') . "\n"
        . "Requested by: {$h['owner_name']}, {$h['owner_designation']}\n"
        . "Email:        {$h['owner_email']} (confirmed)\n"
        . "Mobile:       {$h['owner_phone']}\n";
}

function hostel_email_admins(array $h, string $decisionToken): void
{
    $link = public_url('admin/decision.php?t=' . $decisionToken);
    foreach (q_all("SELECT email FROM users WHERE role = 'admin' AND active = 1") as $a) {
        mail_queue(
            $a['email'],
            'New hostel request: ' . $h['name'],
            "A hostel wants to join HostelCare.\n\n" . hostel_summary_text($h)
            . "\nOpen this page to accept or reject the request:\n$link\n\nThe link works for " . HOSTEL_DECISION_DAYS . " days and can be used once.",
            mail_html('New hostel request', '<pre style="font-family:inherit;white-space:pre-wrap">' . e(hostel_summary_text($h)) . '</pre>', 'Review and decide', $link, 'The page shows the details with Accept and Reject buttons. The link works for ' . HOSTEL_DECISION_DAYS . ' days and can be used once.')
        );
    }
}

/* ------------------------------------------------------------------ */
/*  3. The administrator decides                                       */
/* ------------------------------------------------------------------ */

/** Returns an error message or null on success. */
function hostel_decide(int $hostelId, bool $accept, ?int $adminId, string $note = ''): ?string
{
    $h = hostel_get($hostelId);
    if (!$h || $h['status'] !== 'pending') {
        return 'This request has already been decided or is no longer open.';
    }
    $note = mb_substr(trim($note), 0, 500);
    if (!$accept) {
        q("UPDATE hostels SET status = 'rejected', decided_at = ?, decided_by = ?, decision_note = ?, decision_hash = NULL, decision_expires = NULL WHERE id = ? AND status = 'pending'",
            [now(), $adminId, $note !== '' ? $note : null, $hostelId]);
        mail_queue(
            $h['owner_email'],
            'Your HostelCare request for ' . $h['name'],
            "Hello {$h['owner_name']},\n\nYour request to register \"{$h['name']}\" on HostelCare was not approved."
            . ($note !== '' ? "\n\nReason: $note" : '') . "\n\nYou may submit a new request with corrected details."
        );
        return null;
    }
    [$token, $hash] = new_token();
    q("UPDATE hostels SET status = 'approved', decided_at = ?, decided_by = ?, decision_note = ?, decision_hash = NULL, decision_expires = NULL,
              setup_hash = ?, setup_expires = ? WHERE id = ? AND status = 'pending'",
        [now(), $adminId, $note !== '' ? $note : null, $hash, date('Y-m-d H:i:s', time() + HOSTEL_SETUP_DAYS * 86400), $hostelId]);
    hostel_email_setup_link(hostel_get($hostelId), $token);
    return null;
}

function hostel_email_setup_link(array $h, string $token): void
{
    $link = public_url('hostel/setup.php?t=' . $token);
    mail_queue(
        $h['owner_email'],
        'Your hostel is approved: set up ' . $h['name'],
        "Hello {$h['owner_name']},\n\nGood news: \"{$h['name']}\" has been approved on HostelCare.\n\n"
        . "Open this link to choose your password and open your hostel's dashboard:\n$link\n\n"
        . 'The link works for ' . HOSTEL_SETUP_DAYS . " days and can be used once.\n\n"
        . "In the dashboard you decide your complaint categories, the questions on your complaint form, and you get a QR code for students to scan.",
        mail_html('Your hostel is approved', 'Hello ' . e($h['owner_name']) . ',<br><br><strong>' . e($h['name']) . '</strong> has been approved on HostelCare. Choose your password to open your hostel\'s dashboard.', 'Set up my hostel', $link, 'The link works for ' . HOSTEL_SETUP_DAYS . ' days and can be used once.')
    );
}

/** Sends a fresh setup link for an approved hostel whose owner has not finished yet. */
function hostel_resend_setup(int $hostelId): ?string
{
    $h = hostel_get($hostelId);
    if (!$h || $h['status'] !== 'approved' || $h['owner_user_id']) {
        return 'A new setup link can only be sent to an approved hostel that has not been set up yet.';
    }
    [$token, $hash] = new_token();
    q('UPDATE hostels SET setup_hash = ?, setup_expires = ? WHERE id = ?', [$hash, date('Y-m-d H:i:s', time() + HOSTEL_SETUP_DAYS * 86400), $hostelId]);
    hostel_email_setup_link($h, $token);
    return null;
}

/* ------------------------------------------------------------------ */
/*  4. The warden sets a password and the dashboard opens              */
/* ------------------------------------------------------------------ */

/** @return array{0: ?int, 1: array} new user id (null on failure) and errors */
function hostel_owner_setup(string $token, string $password, string $password2): array
{
    $h = hostel_find_by_token('setup', $token);
    if (!$h || $h['status'] !== 'approved' || $h['owner_user_id']) {
        return [null, ['This setup link is not valid any more. It may have expired or already been used.']];
    }
    if ($password !== $password2) {
        return [null, ['The two passwords do not match.']];
    }
    $uid = null;
    $errors = create_user([
        'hostel_id' => $h['id'], 'login_id' => $h['owner_email'], 'name' => $h['owner_name'], 'email' => $h['owner_email'],
        'phone' => $h['owner_phone'], 'role' => 'warden', 'is_owner' => 1, 'password' => $password,
    ], $uid);
    if ($errors) {
        return [null, $errors];
    }
    q('UPDATE hostels SET owner_user_id = ?, setup_hash = NULL, setup_expires = NULL WHERE id = ?', [$uid, $h['id']]);
    return [$uid, []];
}

/* ------------------------------------------------------------------ */
/*  5. Setup checklist and publishing                                  */
/* ------------------------------------------------------------------ */

/** @return array<string,array{done:bool,required:bool,label:string}> */
function hostel_checklist(int $hostelId): array
{
    $h = hostel_get($hostelId);
    return [
        'blocks'     => ['done' => $h && hostel_blocks_list($h) !== [], 'required' => true, 'label' => 'Add your blocks'],
        'categories' => ['done' => (int) q_val('SELECT COUNT(*) FROM categories WHERE hostel_id = ? AND active = 1', [$hostelId]) > 0, 'required' => true, 'label' => 'Add at least one complaint category'],
        'form'       => ['done' => (int) q_val('SELECT COUNT(*) FROM hostel_fields WHERE hostel_id = ? AND active = 1', [$hostelId]) > 0, 'required' => false, 'label' => 'Add your own questions to the complaint form'],
        'providers'  => ['done' => (int) q_val('SELECT COUNT(*) FROM staff WHERE hostel_id = ? AND active = 1', [$hostelId]) > 0, 'required' => false, 'label' => 'Add nearby service providers (plumber, electrician...)'],
    ];
}

function hostel_publish(int $hostelId): ?string
{
    $h = hostel_get($hostelId);
    if (!$h || !in_array($h['status'], ['approved', 'active'], true)) {
        return 'This hostel cannot be published.';
    }
    foreach (hostel_checklist($hostelId) as $step) {
        if ($step['required'] && !$step['done']) {
            return 'Finish this step first: ' . $step['label'] . '.';
        }
    }
    $token = $h['qr_token'] ?: bin2hex(random_bytes(16));
    $first = $h['status'] === 'approved';
    q("UPDATE hostels SET status = 'active', qr_token = ?, published_at = COALESCE(published_at, ?) WHERE id = ?", [$token, now(), $hostelId]);
    if ($first) {
        $link = public_url('warden/qr.php');
        mail_queue(
            $h['owner_email'],
            $h['name'] . ' is live on HostelCare',
            "Hello {$h['owner_name']},\n\n\"{$h['name']}\" is now live. Print your QR code and put it where students will see it:\n$link\n\nStudents who scan it reach only your hostel's complaint form."
        );
    }
    return null;
}

function hostel_regenerate_qr(int $hostelId): ?string
{
    $h = hostel_get($hostelId);
    if (!$h || $h['status'] !== 'active') {
        return 'Publish the hostel first.';
    }
    q('UPDATE hostels SET qr_token = ? WHERE id = ?', [bin2hex(random_bytes(16)), $hostelId]);
    return null;
}

/** The hostel a scanned QR code belongs to; only active hostels, nothing else leaks. */
function hostel_by_qr(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return null;
    }
    return q_one("SELECT * FROM hostels WHERE qr_token = ? AND status = 'active'", [$token]);
}

function hostel_qr_url(array $h): string
{
    return public_url('h.php?c=' . $h['qr_token']);
}

function hostel_set_suspended(int $hostelId, bool $suspend): ?string
{
    $h = hostel_get($hostelId);
    if (!$h) {
        return 'Hostel not found.';
    }
    if ($suspend) {
        if ($h['status'] !== 'active' && $h['status'] !== 'approved') {
            return 'Only an active or approved hostel can be suspended.';
        }
        q("UPDATE hostels SET status = 'suspended' WHERE id = ?", [$hostelId]);
    } else {
        if ($h['status'] !== 'suspended') {
            return 'This hostel is not suspended.';
        }
        q('UPDATE hostels SET status = ? WHERE id = ?', [$h['published_at'] ? 'active' : 'approved', $hostelId]);
    }
    return null;
}

/** Removes requests whose email was never confirmed. */
function hostel_cleanup(): void
{
    q("DELETE FROM hostels WHERE status = 'unverified' AND created_at < ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
}
