<?php
declare(strict_types=1);

/*
 * Complaint rules. Every function that reads or writes complaints is limited to ONE hostel:
 * the hostel of the signed-in user. A user of one hostel can never read or change another hostel's data.
 */

/* ------------------------------------------------------------------ */
/*  Lifecycle rules                                                    */
/* ------------------------------------------------------------------ */

function status_flow(): array
{
    return [
        'submitted'    => ['acknowledged', 'assigned', 'in_progress', 'resolved'],
        'acknowledged' => ['assigned', 'in_progress', 'resolved'],
        'assigned'     => ['in_progress', 'resolved'],
        'in_progress'  => ['resolved'],
        'resolved'     => ['closed', 'reopened'],
        'closed'       => ['reopened'],
        'reopened'     => ['acknowledged', 'assigned', 'in_progress', 'resolved'],
    ];
}

function is_open_status(string $status): bool
{
    return !in_array($status, ['resolved', 'closed'], true);
}

function is_overdue(array $c): bool
{
    return is_open_status((string) $c['status']) && strtotime((string) $c['due_at']) < time();
}

function categories_active(int $hostelId): array
{
    return q_all('SELECT * FROM categories WHERE hostel_id = ? AND active = 1 ORDER BY name', [$hostelId]);
}

function staff_active(int $hostelId): array
{
    return q_all('SELECT * FROM staff WHERE hostel_id = ? AND active = 1 ORDER BY name', [$hostelId]);
}

/* ------------------------------------------------------------------ */
/*  Uploads                                                            */
/* ------------------------------------------------------------------ */

function normalize_files(?array $field): array
{
    if (!$field || !isset($field['name'])) {
        return [];
    }
    $files = [];
    if (is_array($field['name'])) {
        foreach ($field['name'] as $i => $name) {
            $files[] = [
                'name' => (string) $name, 'tmp_name' => (string) $field['tmp_name'][$i],
                'size' => (int) $field['size'][$i], 'error' => (int) $field['error'][$i],
            ];
        }
    } else {
        $files[] = [
            'name' => (string) $field['name'], 'tmp_name' => (string) $field['tmp_name'],
            'size' => (int) $field['size'], 'error' => (int) $field['error'],
        ];
    }
    return array_values(array_filter($files, fn($f) => $f['error'] !== UPLOAD_ERR_NO_FILE));
}

/** @return array{0: array, 1: array} valid files with detected type, and error messages */
function validate_uploads(array $files): array
{
    $errors = [];
    $valid = [];
    $max = (int) cfg('rules.max_files');
    $maxBytes = (int) cfg('rules.max_file_mb') * 1024 * 1024;
    if (count($files) > $max) {
        return [[], ['You can attach at most ' . $max . ' photos.']];
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = '"' . $f['name'] . '" could not be uploaded.';
            continue;
        }
        if ($f['size'] > $maxBytes) {
            $errors[] = '"' . $f['name'] . '" is larger than ' . cfg('rules.max_file_mb') . ' MB.';
            continue;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset($allowed[$mime]) || @getimagesize($f['tmp_name']) === false) {
            $errors[] = '"' . $f['name'] . '" is not a valid JPG, PNG or WebP image.';
            continue;
        }
        $f['mime'] = $mime;
        $f['ext'] = $allowed[$mime];
        $valid[] = $f;
    }
    return [$valid, $errors];
}

function hc_move_file(string $from, string $to): bool
{
    if (is_uploaded_file($from)) {
        return move_uploaded_file($from, $to);
    }
    if (defined('HC_TESTING')) {
        return copy($from, $to);
    }
    return false;
}

function save_attachments(int $complaintId, array $validFiles): void
{
    $dir = HC_ROOT . '/storage/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    foreach ($validFiles as $f) {
        $stored = bin2hex(random_bytes(16)) . '.' . $f['ext'];
        if (!hc_move_file($f['tmp_name'], $dir . '/' . $stored)) {
            continue;
        }
        q(
            'INSERT INTO attachments (complaint_id, original_name, stored_name, mime, size_bytes, created_at) VALUES (?,?,?,?,?,?)',
            [$complaintId, mb_substr(basename($f['name']), 0, 200), $stored, $f['mime'], $f['size'], now()]
        );
    }
}

/* ------------------------------------------------------------------ */
/*  Reading                                                            */
/* ------------------------------------------------------------------ */

function get_complaint(int $id): ?array
{
    return q_one(
        'SELECT c.*, cat.name AS category, cat.sla_hours, u.name AS student_name, u.login_id AS student_login_id,
                u.block AS student_block, u.room AS student_room, u.phone AS student_phone,
                s.name AS staff_name, s.trade AS staff_trade, s.phone AS staff_phone
         FROM complaints c
         JOIN categories cat ON cat.id = c.category_id
         JOIN users u ON u.id = c.student_id
         LEFT JOIN staff s ON s.id = c.assigned_staff_id
         WHERE c.id = ?',
        [$id]
    );
}

/**
 * Who may open a complaint: a student only their own; a warden only complaints of their own hostel, and
 * confidential ones only if they are the hostel's owner. Platform administrators can open none.
 */
function can_view_complaint(array $user, array $c): bool
{
    if (!in_array($user['role'], ['student', 'warden'], true) || (int) $user['hostel_id'] !== (int) $c['hostel_id']) {
        return false;
    }
    if ($user['role'] === 'student') {
        return (int) $c['student_id'] === (int) $user['id'];
    }
    return !(int) $c['is_confidential'] || (int) $user['is_owner'] === 1;
}

function complaint_timeline(int $id, bool $includeInternal): array
{
    $sql = 'SELECT t.*, usr.name AS user_name, usr.role AS user_role
            FROM complaint_updates t LEFT JOIN users usr ON usr.id = t.user_id
            WHERE t.complaint_id = ?' . ($includeInternal ? '' : ' AND t.is_internal = 0') . ' ORDER BY t.id ASC';
    return q_all($sql, [$id]);
}

function complaint_attachments(int $id): array
{
    return q_all('SELECT * FROM attachments WHERE complaint_id = ? ORDER BY id', [$id]);
}

function mask_confidential(array $row): array
{
    if ((int) $row['is_confidential']) {
        $row['title'] = 'Confidential complaint';
        $row['description'] = '';
        $row['location'] = '';
        $row['extra_json'] = null;
        foreach (['student_name', 'block', 'room'] as $k) {
            if (isset($row[$k])) {
                $row[$k] = 'Hidden';
            }
        }
    }
    return $row;
}

function must_mask(array $user): bool
{
    return $user['role'] === 'warden' && !(int) $user['is_owner'];
}

/** Returns [rows, total]. Always limited to the user's own hostel. */
function list_complaints(array $user, array $f, int $page = 1): array
{
    if (!in_array($user['role'], ['student', 'warden'], true) || empty($user['hostel_id'])) {
        return [[], 0];
    }
    $where = ['c.hostel_id = ?'];
    $p = [(int) $user['hostel_id']];
    if ($user['role'] === 'student') {
        $where[] = 'c.student_id = ?';
        $p[] = $user['id'];
    }
    $status = $f['status'] ?? '';
    if ($status === 'open') {
        $where[] = "c.status NOT IN ('resolved','closed')";
    } elseif ($status !== '' && isset(status_labels()[$status])) {
        $where[] = 'c.status = ?';
        $p[] = $status;
    }
    if (!empty($f['category_id'])) {
        $where[] = 'c.category_id = ?';
        $p[] = (int) $f['category_id'];
    }
    if (!empty($f['priority']) && isset(priority_labels()[$f['priority']])) {
        $where[] = 'c.priority = ?';
        $p[] = $f['priority'];
    }
    if (!empty($f['block'])) {
        $where[] = 'u.block = ?';
        $p[] = $f['block'];
    }
    if (!empty($f['overdue'])) {
        $where[] = "c.status NOT IN ('resolved','closed') AND c.due_at < ?";
        $p[] = now();
    }
    if (!empty($f['q'])) {
        $needle = trim((string) $f['q']);
        $digits = preg_replace('/\D/', '', $needle);
        if (preg_match('/^(HC-?)?\d+$/i', $needle) && $digits !== '') {
            $where[] = 'c.id = ?';
            $p[] = (int) $digits;
        } else {
            $where[] = 'c.title LIKE ?';
            $p[] = '%' . $needle . '%';
        }
    }
    $w = implode(' AND ', $where);
    $from = 'FROM complaints c JOIN categories cat ON cat.id = c.category_id
             JOIN users u ON u.id = c.student_id LEFT JOIN staff s ON s.id = c.assigned_staff_id';
    $total = (int) q_val("SELECT COUNT(*) $from WHERE $w", $p);
    $size = (int) cfg('rules.page_size');
    $offset = max(0, ($page - 1) * $size);

    if ($user['role'] === 'student') {
        $order = 'c.created_at DESC, c.id DESC';
        $orderParams = [];
    } else {
        $order = "CASE WHEN c.status NOT IN ('resolved','closed') AND c.due_at < ? THEN 0 ELSE 1 END,
                  CASE WHEN c.status IN ('resolved','closed') THEN 1 ELSE 0 END,
                  CASE c.priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END, c.created_at ASC";
        $orderParams = [now()];
    }
    $rows = q_all(
        "SELECT c.*, cat.name AS category, u.name AS student_name, u.block, u.room, s.name AS staff_name
         $from WHERE $w ORDER BY $order LIMIT $size OFFSET $offset",
        array_merge($p, $orderParams)
    );
    if (must_mask($user)) {
        $rows = array_map('mask_confidential', $rows);
    }
    return [$rows, $total];
}

/* ------------------------------------------------------------------ */
/*  Writing                                                            */
/* ------------------------------------------------------------------ */

/** Builds "Block A, Room 101" from the hostel's block list and the place the student typed. */
function build_location(array $hostel, string $block, string $place): array
{
    $block = trim($block);
    $place = trim($place);
    $blocks = hostel_blocks_list($hostel);
    if ($blocks && !in_array($block, $blocks, true)) {
        return ['', 'Choose your block from the list.'];
    }
    if (mb_strlen($place) < 1 || mb_strlen($place) > 100) {
        return ['', 'Enter the room or place of the problem.'];
    }
    return [($block !== '' ? 'Block ' . $block . ', ' : '') . $place, null];
}

/**
 * $d: category_id, title, description, priority, is_confidential, and either location or block + place,
 * plus the answers to the hostel's own questions (inputs f_<id>).
 */
function create_complaint(array $student, array $d, array $files, ?array &$errors = null): ?int
{
    $errors = [];
    $hostelId = (int) ($student['hostel_id'] ?? 0);
    $hostel = $student['role'] === 'student' && $hostelId ? hostel_get($hostelId) : null;
    if (!$hostel || $hostel['status'] !== 'active') {
        $errors[] = 'This hostel is not accepting complaints.';
        return null;
    }
    $cat = q_one('SELECT * FROM categories WHERE id = ? AND hostel_id = ? AND active = 1', [(int) ($d['category_id'] ?? 0), $hostelId]);
    if (!$cat) {
        $errors[] = 'Choose a category.';
    }
    $title = trim((string) ($d['title'] ?? ''));
    $desc = trim((string) ($d['description'] ?? ''));
    if (mb_strlen($title) < 5 || mb_strlen($title) > 160) {
        $errors[] = 'Title must be 5 to 160 characters.';
    }
    if (mb_strlen($desc) < 10 || mb_strlen($desc) > 4000) {
        $errors[] = 'Describe the problem in 10 to 4000 characters.';
    }
    if (isset($d['location']) && !isset($d['place'])) {
        $loc = trim((string) $d['location']);
        if (mb_strlen($loc) < 2 || mb_strlen($loc) > 160) {
            $errors[] = 'Enter the location of the problem.';
        }
    } else {
        [$loc, $locErr] = build_location($hostel, (string) ($d['block'] ?? ''), (string) ($d['place'] ?? ''));
        if ($locErr) {
            $errors[] = $locErr;
        }
    }
    [$answers, $answerErrors] = form_collect_answers($hostelId, $d);
    $errors = array_merge($errors, $answerErrors);
    $priority = (string) ($d['priority'] ?? '');
    if (!isset(priority_labels()[$priority])) {
        $priority = $cat['default_priority'] ?? 'medium';
    }
    [$valid, $uploadErrors] = validate_uploads($files);
    $errors = array_merge($errors, $uploadErrors);
    if ($errors) {
        return null;
    }

    $due = date('Y-m-d H:i:s', time() + (int) $cat['sla_hours'] * 3600);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q(
            'INSERT INTO complaints (hostel_id, student_id, category_id, title, description, location, extra_json, priority, status,
                                     is_confidential, due_at, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$hostelId, $student['id'], $cat['id'], $title, $desc, $loc, $answers ? json_encode($answers, JSON_UNESCAPED_UNICODE) : null,
             $priority, 'submitted', !empty($d['is_confidential']) ? 1 : 0, $due, now(), now()]
        );
        $id = (int) $pdo->lastInsertId();
        q(
            'INSERT INTO complaint_updates (complaint_id, user_id, old_status, new_status, note, is_internal, created_at)
             VALUES (?,?,?,?,?,0,?)',
            [$id, $student['id'], null, 'submitted', 'Complaint registered', now()]
        );
        save_attachments($id, $valid);
        notify_wardens($hostelId, $id, ticket_no($id) . ' new ' . $priority . ' priority complaint: ' . $cat['name']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $id;
}

function apply_transition(array $c, string $to, ?int $userId, ?string $note, bool $internal = false): void
{
    $set = ['status = ?', 'updated_at = ?'];
    $p = [$to, now()];
    if ($to === 'acknowledged' && !$c['acknowledged_at']) {
        $set[] = 'acknowledged_at = ?';
        $p[] = now();
    }
    if ($to === 'resolved') {
        $set[] = 'resolved_at = ?';
        $p[] = now();
    }
    if ($to === 'closed') {
        $set[] = 'closed_at = ?';
        $p[] = now();
    }
    if ($to === 'reopened') {
        $sla = (int) q_val('SELECT sla_hours FROM categories WHERE id = ?', [$c['category_id']]);
        $set[] = 'resolved_at = NULL';
        $set[] = 'closed_at = NULL';
        $set[] = 'rating = NULL';
        $set[] = 'feedback = NULL';
        $set[] = 'due_at = ?';
        $p[] = date('Y-m-d H:i:s', time() + $sla * 3600);
    }
    $p[] = $c['id'];
    q('UPDATE complaints SET ' . implode(', ', $set) . ' WHERE id = ?', $p);
    q(
        'INSERT INTO complaint_updates (complaint_id, user_id, old_status, new_status, note, is_internal, created_at)
         VALUES (?,?,?,?,?,?,?)',
        [$c['id'], $userId, $c['status'], $to, $note, $internal ? 1 : 0, now()]
    );
}

/** Returns an error message or null on success. */
function staff_set_status(array $user, int $id, string $to, ?string $note): ?string
{
    $c = get_complaint($id);
    if (!$c || !is_staff_role($user['role']) || !can_view_complaint($user, $c)) {
        return 'You cannot update this complaint.';
    }
    if (!in_array($to, ['acknowledged', 'in_progress', 'resolved'], true)) {
        return 'That status cannot be set manually.';
    }
    if (!in_array($to, status_flow()[$c['status']] ?? [], true)) {
        return 'A complaint cannot move from ' . status_label($c['status']) . ' to ' . status_label($to) . '.';
    }
    $note = trim((string) $note);
    if ($to === 'resolved' && mb_strlen($note) < 5) {
        return 'Add a short note describing what was done before resolving.';
    }
    apply_transition($c, $to, (int) $user['id'], $note !== '' ? $note : null);
    notify((int) $c['student_id'], $id, ticket_no($id) . ' is now ' . status_label($to));
    return null;
}

function assign_staff(array $user, int $id, int $staffId, ?string $note): ?string
{
    $c = get_complaint($id);
    if (!$c || !is_staff_role($user['role']) || !can_view_complaint($user, $c)) {
        return 'You cannot update this complaint.';
    }
    if (!is_open_status($c['status'])) {
        return 'Resolved or closed complaints cannot be reassigned.';
    }
    $staff = q_one('SELECT * FROM staff WHERE id = ? AND hostel_id = ? AND active = 1', [$staffId, (int) $c['hostel_id']]);
    if (!$staff) {
        return 'Choose a person from your list of service providers.';
    }
    q('UPDATE complaints SET assigned_staff_id = ?, updated_at = ? WHERE id = ?', [$staffId, now(), $id]);
    $text = 'Assigned to ' . $staff['name'] . ' (' . $staff['trade'] . ')' . (trim((string) $note) !== '' ? '. ' . trim((string) $note) : '');
    if (in_array($c['status'], ['submitted', 'acknowledged', 'reopened'], true)) {
        apply_transition($c, 'assigned', (int) $user['id'], $text);
    } else {
        q(
            'INSERT INTO complaint_updates (complaint_id, user_id, old_status, new_status, note, is_internal, created_at)
             VALUES (?,?,?,?,?,0,?)',
            [$id, $user['id'], $c['status'], $c['status'], $text, now()]
        );
    }
    notify((int) $c['student_id'], $id, ticket_no($id) . ' assigned to ' . $staff['name']);
    return null;
}

function add_note(array $user, int $id, string $note, bool $internal): ?string
{
    $c = get_complaint($id);
    $note = trim($note);
    if (!$c || !can_view_complaint($user, $c)) {
        return 'You cannot comment on this complaint.';
    }
    if (mb_strlen($note) < 2 || mb_strlen($note) > 2000) {
        return 'Write a note between 2 and 2000 characters.';
    }
    if ($user['role'] === 'student') {
        $internal = false;
        if ($c['status'] === 'closed') {
            return 'This complaint is closed. Reopen it to add more details.';
        }
    }
    q(
        'INSERT INTO complaint_updates (complaint_id, user_id, old_status, new_status, note, is_internal, created_at)
         VALUES (?,?,?,?,?,?,?)',
        [$id, $user['id'], null, null, $note, $internal ? 1 : 0, now()]
    );
    q('UPDATE complaints SET updated_at = ? WHERE id = ?', [now(), $id]);
    if (!$internal) {
        if ($user['role'] === 'student') {
            notify_wardens((int) $c['hostel_id'], $id, ticket_no($id) . ' student added a comment');
        } else {
            notify((int) $c['student_id'], $id, ticket_no($id) . ' has a new message from the warden');
        }
    }
    return null;
}

function student_close(array $user, int $id, int $rating, ?string $feedback): ?string
{
    $c = get_complaint($id);
    if (!$c || $user['role'] !== 'student' || (int) $c['student_id'] !== (int) $user['id']) {
        return 'You cannot update this complaint.';
    }
    if ($c['status'] !== 'resolved') {
        return 'Only a resolved complaint can be confirmed and closed.';
    }
    if ($rating < 1 || $rating > 5) {
        return 'Choose a rating from 1 to 5.';
    }
    apply_transition($c, 'closed', (int) $user['id'], 'Student confirmed the fix');
    q('UPDATE complaints SET rating = ?, feedback = ? WHERE id = ?', [$rating, trim((string) $feedback) ?: null, $id]);
    notify_wardens((int) $c['hostel_id'], $id, ticket_no($id) . ' closed with a rating of ' . $rating . '/5');
    return null;
}

function student_reopen(array $user, int $id, string $reason): ?string
{
    $c = get_complaint($id);
    if (!$c || $user['role'] !== 'student' || (int) $c['student_id'] !== (int) $user['id']) {
        return 'You cannot update this complaint.';
    }
    if (!in_array($c['status'], ['resolved', 'closed'], true)) {
        return 'Only a resolved or closed complaint can be reopened.';
    }
    $days = (int) cfg('rules.reopen_days');
    if ($c['resolved_at'] && time() - strtotime($c['resolved_at']) > $days * 86400) {
        return 'This complaint was resolved more than ' . $days . ' days ago. Please raise a new complaint.';
    }
    if (mb_strlen(trim($reason)) < 5) {
        return 'Tell us what is still wrong (at least 5 characters).';
    }
    apply_transition($c, 'reopened', (int) $user['id'], trim($reason));
    notify_wardens((int) $c['hostel_id'], $id, ticket_no($id) . ' was reopened by the student');
    return null;
}

/** Closes resolved complaints that received no response, in every hostel. Returns the number closed. */
function auto_close_resolved(): int
{
    $days = (int) cfg('rules.auto_close_days');
    $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
    $rows = q_all("SELECT id FROM complaints WHERE status = 'resolved' AND resolved_at <= ?", [$cutoff]);
    foreach ($rows as $r) {
        $c = get_complaint((int) $r['id']);
        apply_transition($c, 'closed', null, 'Closed automatically after ' . $days . ' days without a reply');
        notify((int) $c['student_id'], (int) $c['id'], ticket_no((int) $c['id']) . ' was closed automatically');
    }
    return count($rows);
}

/* ------------------------------------------------------------------ */
/*  Analytics for ONE hostel: every figure comes from stored complaints */
/* ------------------------------------------------------------------ */

function analytics(int $hostelId): array
{
    $byStatus = [];
    foreach (q_all('SELECT status, COUNT(*) AS n FROM complaints WHERE hostel_id = ? GROUP BY status', [$hostelId]) as $r) {
        $byStatus[$r['status']] = (int) $r['n'];
    }
    $total = array_sum($byStatus);
    $overdue = (int) q_val(
        "SELECT COUNT(*) FROM complaints WHERE hostel_id = ? AND status NOT IN ('resolved','closed') AND due_at < ?",
        [$hostelId, now()]
    );
    $byCategory = q_all(
        'SELECT cat.name, COUNT(c.id) AS n FROM categories cat JOIN complaints c ON c.category_id = cat.id
         WHERE c.hostel_id = ? GROUP BY cat.id, cat.name ORDER BY n DESC',
        [$hostelId]
    );
    $byBlock = q_all(
        "SELECT u.block AS name, COUNT(c.id) AS n FROM complaints c JOIN users u ON u.id = c.student_id
         WHERE c.hostel_id = ? AND u.block IS NOT NULL GROUP BY u.block ORDER BY n DESC",
        [$hostelId]
    );

    $hours = [];
    $onTime = 0;
    foreach (q_all('SELECT created_at, resolved_at, due_at FROM complaints WHERE hostel_id = ? AND resolved_at IS NOT NULL', [$hostelId]) as $r) {
        $hours[] = (strtotime($r['resolved_at']) - strtotime($r['created_at'])) / 3600;
        if (strtotime($r['resolved_at']) <= strtotime($r['due_at'])) {
            $onTime++;
        }
    }
    sort($hours);
    $n = count($hours);
    $median = $n === 0 ? null : ($n % 2 ? $hours[intdiv($n, 2)] : ($hours[$n / 2 - 1] + $hours[$n / 2]) / 2);

    $ratingRow = q_one('SELECT AVG(rating) AS avg, COUNT(rating) AS n FROM complaints WHERE hostel_id = ? AND rating IS NOT NULL', [$hostelId]);

    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[date('Y-m', strtotime("first day of -$i month"))] = 0;
    }
    $since = date('Y-m-01 00:00:00', strtotime('first day of -5 month'));
    foreach (q_all('SELECT created_at FROM complaints WHERE hostel_id = ? AND created_at >= ?', [$hostelId, $since]) as $r) {
        $key = substr($r['created_at'], 0, 7);
        if (isset($months[$key])) {
            $months[$key]++;
        }
    }

    $staff = q_all(
        "SELECT s.name, s.trade,
                SUM(CASE WHEN c.status NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS open_n,
                SUM(CASE WHEN c.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS done_n
         FROM staff s LEFT JOIN complaints c ON c.assigned_staff_id = s.id
         WHERE s.hostel_id = ? AND s.active = 1 GROUP BY s.id, s.name, s.trade ORDER BY s.name",
        [$hostelId]
    );

    return [
        'total'        => $total,
        'by_status'    => $byStatus,
        'open'         => $total - ($byStatus['resolved'] ?? 0) - ($byStatus['closed'] ?? 0),
        'overdue'      => $overdue,
        'by_category'  => $byCategory,
        'by_block'     => $byBlock,
        'resolved_n'   => $n,
        'avg_hours'    => $n ? array_sum($hours) / $n : null,
        'median_hours' => $median,
        'sla_rate'     => $n ? $onTime / $n : null,
        'avg_rating'   => $ratingRow && $ratingRow['n'] ? (float) $ratingRow['avg'] : null,
        'rating_n'     => (int) ($ratingRow['n'] ?? 0),
        'months'       => $months,
        'staff'        => $staff,
    ];
}

function export_rows(array $viewer, ?string $from, ?string $to): array
{
    if ($viewer['role'] !== 'warden') {
        return [];
    }
    $where = ['c.hostel_id = ?'];
    $p = [(int) $viewer['hostel_id']];
    if ($from) {
        $where[] = 'c.created_at >= ?';
        $p[] = $from . ' 00:00:00';
    }
    if ($to) {
        $where[] = 'c.created_at <= ?';
        $p[] = $to . ' 23:59:59';
    }
    $rows = q_all(
        'SELECT c.*, cat.name AS category, u.name AS student_name, u.block, u.room, s.name AS staff_name
         FROM complaints c JOIN categories cat ON cat.id = c.category_id JOIN users u ON u.id = c.student_id
         LEFT JOIN staff s ON s.id = c.assigned_staff_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.id',
        $p
    );
    return must_mask($viewer) ? array_map('mask_confidential', $rows) : $rows;
}

/** Counts shown to the platform administrator: numbers only, never complaint content. */
function platform_hostel_counts(int $hostelId): array
{
    return [
        'students'   => (int) q_val("SELECT COUNT(*) FROM users WHERE hostel_id = ? AND role = 'student'", [$hostelId]),
        'complaints' => (int) q_val('SELECT COUNT(*) FROM complaints WHERE hostel_id = ?', [$hostelId]),
        'open'       => (int) q_val("SELECT COUNT(*) FROM complaints WHERE hostel_id = ? AND status NOT IN ('resolved','closed')", [$hostelId]),
    ];
}
