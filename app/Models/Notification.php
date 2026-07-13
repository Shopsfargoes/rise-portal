<?php
// ============================================================
// RISE CAPITAL GROUP — Notification Model
// ============================================================

namespace Rise\Models;

class Notification
{
    // ── Fetch notifications for a user ────────────────────

    public static function findByUser(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $where  = ['user_id = ?'];
        $params = [$userId];

        if ($unreadOnly) {
            $where[] = 'is_read = 0';
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT * FROM notifications
             WHERE {$whereSQL}
             ORDER BY created_at DESC
             LIMIT {$limit}",
            $params
        );
    }

    // ── Unread count ──────────────────────────────────────

    public static function unreadCount(int $userId): int
    {
        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    // ── Create a notification ─────────────────────────────

    public static function create(array $data): int|string
    {
        return db()->insert('notifications', [
            'user_id'      => $data['user_id'],
            'type'         => $data['type'],
            'title'        => $data['title'],
            'message'      => $data['message'],
            'related_type' => $data['related_type'] ?? null,
            'related_id'   => $data['related_id']   ?? null,
            'is_read'      => 0,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Mark single notification as read ──────────────────

    public static function markRead(int $id, int $userId): void
    {
        db()->update('notifications', ['is_read' => 1], ['id' => $id, 'user_id' => $userId]);
    }

    // ── Mark all notifications as read ────────────────────

    public static function markAllRead(int $userId): void
    {
        db()->query(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    // ── Notify all admins ─────────────────────────────────

    public static function notifyAdmins(string $type, string $title, string $message,
                                         ?string $relatedType = null, ?int $relatedId = null): void
    {
        $admins = db()->fetchAll(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active'"
        );

        $now = date('Y-m-d H:i:s');

        foreach ($admins as $admin) {
            db()->insert('notifications', [
                'user_id'      => $admin['id'],
                'type'         => $type,
                'title'        => $title,
                'message'      => $message,
                'related_type' => $relatedType,
                'related_id'   => $relatedId,
                'is_read'      => 0,
                'created_at'   => $now,
            ]);
        }
    }

    // ── Icon per notification type ────────────────────────

    public static function icon(string $type): string
    {
        return match(true) {
            str_contains($type, 'deposit')      => '💰',
            str_contains($type, 'withdrawal')   => '💸',
            str_contains($type, 'transaction')  => '💳',
            str_contains($type, 'investment')   => '📈',
            str_contains($type, 'distribution') => '📤',
            str_contains($type, 'message')      => '💬',
            str_contains($type, 'invite')       => '✉️',
            default                             => '🔔',
        };
    }

    // ── Badge class per type ──────────────────────────────

    public static function badgeClass(string $type): string
    {
        return match(true) {
            str_contains($type, 'deposit')      => 'badge-green',
            str_contains($type, 'withdrawal')   => 'badge-orange',
            str_contains($type, 'rejected')     => 'badge-red',
            str_contains($type, 'confirmed')    => 'badge-green',
            str_contains($type, 'investment')   => 'badge-blue',
            str_contains($type, 'distribution') => 'badge-blue',
            str_contains($type, 'message')      => 'badge-gold',
            default                             => 'badge-grey',
        };
    }

    // ── Link for a notification ───────────────────────────

    public static function link(array $notif, string $role): string
    {
        $base = APP_URL;

        return match(true) {
            str_contains($notif['type'], 'deposit')
            || str_contains($notif['type'], 'withdrawal') =>
                $role === 'admin'
                    ? "{$base}/admin/wallet/pending.php"
                    : "{$base}/investor/transactions.php",

            str_contains($notif['type'], 'investment') =>
                $role === 'admin'
                    ? "{$base}/admin/investments/index.php"
                    : "{$base}/investor/dashboard.php",

            str_contains($notif['type'], 'distribution') =>
                $role === 'admin'
                    ? "{$base}/admin/distributions/index.php"
                    : "{$base}/investor/distributions.php",

            str_contains($notif['type'], 'message') =>
                $role === 'admin'
                    ? "{$base}/admin/messages/index.php"
                    : "{$base}/investor/messages.php",

            default => $role === 'admin'
                ? "{$base}/admin/dashboard.php"
                : "{$base}/investor/dashboard.php",
        };
    }
}