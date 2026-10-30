<?php
declare(strict_types=1);

function notify(int $userId, ?int $complaintId, string $message): void
{
    q(
        'INSERT INTO notifications (user_id, complaint_id, message, is_read, created_at) VALUES (?, ?, ?, 0, ?)',
        [$userId, $complaintId, mb_substr($message, 0, 250), now()]
    );
    sms_queue_for_user($userId, $message);
}

/** Tells every active warden of one hostel. */
function notify_wardens(int $hostelId, int $complaintId, string $message): void
{
    foreach (q_all("SELECT id FROM users WHERE role = 'warden' AND active = 1 AND hostel_id = ?", [$hostelId]) as $w) {
        notify((int) $w['id'], $complaintId, $message);
    }
}

function unread_count(int $userId): int
{
    return (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
}

function mark_all_read(int $userId): void
{
    q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
}

function list_notifications(int $userId, int $limit = 50): array
{
    return q_all(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit,
        [$userId]
    );
}
