<?php
declare(strict_types=1);

/* Each hostel designs its own extra questions on the complaint form: dropdown, short text or long text. */

function field_types(): array
{
    return ['dropdown' => 'Dropdown (choose one)', 'text' => 'Short text', 'textarea' => 'Long text'];
}

function form_fields(int $hostelId, bool $activeOnly = true): array
{
    return q_all(
        'SELECT * FROM hostel_fields WHERE hostel_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort_order, id',
        [$hostelId]
    );
}

function field_options(array $field): array
{
    $out = [];
    foreach (preg_split('/\R/', (string) ($field['options'] ?? '')) as $o) {
        $o = trim($o);
        if ($o !== '' && !in_array($o, $out, true)) {
            $out[] = $o;
        }
    }
    return $out;
}

/** Adds or updates a question. Returns an error message or null. */
function form_field_save(int $hostelId, array $d, ?int $id = null): ?string
{
    $label = trim((string) ($d['label'] ?? ''));
    $type = (string) ($d['field_type'] ?? '');
    if (mb_strlen($label) < 2 || mb_strlen($label) > 80) {
        return 'Write the question (2 to 80 characters).';
    }
    if (!isset(field_types()[$type])) {
        return 'Choose how students should answer.';
    }
    $options = null;
    if ($type === 'dropdown') {
        $list = field_options(['options' => (string) ($d['options'] ?? '')]);
        if (count($list) < 2) {
            return 'A dropdown needs at least two options, one per line.';
        }
        if (count($list) > 30) {
            return 'A dropdown can have at most 30 options.';
        }
        foreach ($list as $o) {
            if (mb_strlen($o) > 80) {
                return 'Each option must be 80 characters or fewer.';
            }
        }
        $options = implode("\n", $list);
    }
    $required = !empty($d['is_required']) ? 1 : 0;
    if ($id === null) {
        if ((int) q_val('SELECT COUNT(*) FROM hostel_fields WHERE hostel_id = ?', [$hostelId]) >= 15) {
            return 'You can add at most 15 questions.';
        }
        $next = (int) q_val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM hostel_fields WHERE hostel_id = ?', [$hostelId]);
        q('INSERT INTO hostel_fields (hostel_id, label, field_type, options, is_required, sort_order, active) VALUES (?,?,?,?,?,?,1)',
            [$hostelId, $label, $type, $options, $required, $next]);
        return null;
    }
    $own = q_val('SELECT 1 FROM hostel_fields WHERE id = ? AND hostel_id = ?', [$id, $hostelId]);
    if ($own === null) {
        return 'That question does not exist.';
    }
    q('UPDATE hostel_fields SET label = ?, field_type = ?, options = ?, is_required = ? WHERE id = ? AND hostel_id = ?',
        [$label, $type, $options, $required, $id, $hostelId]);
    return null;
}

function form_field_toggle(int $hostelId, int $id): void
{
    q('UPDATE hostel_fields SET active = 1 - active WHERE id = ? AND hostel_id = ?', [$id, $hostelId]);
}

function form_field_delete(int $hostelId, int $id): void
{
    q('DELETE FROM hostel_fields WHERE id = ? AND hostel_id = ?', [$id, $hostelId]);
}

function form_field_move(int $hostelId, int $id, string $dir): void
{
    $rows = form_fields($hostelId, false);
    $ids = array_map(fn($r) => (int) $r['id'], $rows);
    $i = array_search($id, $ids, true);
    if ($i === false) {
        return;
    }
    $j = $dir === 'up' ? $i - 1 : $i + 1;
    if ($j < 0 || $j >= count($ids)) {
        return;
    }
    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
    foreach ($ids as $order => $fid) {
        q('UPDATE hostel_fields SET sort_order = ? WHERE id = ? AND hostel_id = ?', [$order + 1, $fid, $hostelId]);
    }
}

/**
 * Reads the answers a student typed for the hostel's questions (inputs named f_<id>).
 * @return array{0: array<int,array{label:string,value:string}>, 1: string[]} answers and errors
 */
function form_collect_answers(int $hostelId, array $input): array
{
    $answers = [];
    $errors = [];
    foreach (form_fields($hostelId) as $f) {
        $raw = $input['f_' . $f['id']] ?? '';
        $val = is_string($raw) ? trim($raw) : '';
        if ($val === '') {
            if ($f['is_required']) {
                $errors[] = '"' . $f['label'] . '" is required.';
            }
            continue;
        }
        if ($f['field_type'] === 'dropdown' && !in_array($val, field_options($f), true)) {
            $errors[] = 'Choose one of the listed options for "' . $f['label'] . '".';
            continue;
        }
        $max = $f['field_type'] === 'textarea' ? 2000 : 200;
        if (mb_strlen($val) > $max) {
            $errors[] = '"' . $f['label'] . '" is too long (maximum ' . $max . ' characters).';
            continue;
        }
        $answers[] = ['label' => $f['label'], 'value' => $val];
    }
    return [$answers, $errors];
}

function form_answers_decode(?string $json): array
{
    $a = $json ? json_decode($json, true) : null;
    return is_array($a) ? array_values(array_filter($a, fn($x) => is_array($x) && isset($x['label'], $x['value']))) : [];
}
