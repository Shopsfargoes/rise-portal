<?php
// ============================================================
// RISE CAPITAL GROUP — Message Model
// ============================================================

namespace Rise\Models;

class Message
{
    // ── Get all messages in a thread ──────────────────────

    public static function findByThread(int $threadId): array
    {
        return db()->fetchAll(
            "SELECT m.*,
                    u.email       AS sender_email,
                    u.role        AS sender_role,
                    up.first_name AS sender_first,
                    up.last_name  AS sender_last,
                    up.avatar_path
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             LEFT JOIN user_profiles up ON up.user_id = m.sender_id
             WHERE m.thread_id = ?
             ORDER BY m.created_at ASC",
            [$threadId]
        );
    }

    // ── Create a message ──────────────────────────────────

    public static function create(array $data): int|string
    {
        return db()->insert('messages', [
            'uuid'          => uuid4(),
            'thread_id'     => $data['thread_id'],
            'sender_id'     => $data['sender_id'],
            'body'          => $data['body'],
            'attachment_id' => $data['attachment_id'] ?? null,
            'is_read'       => 0,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Mark all messages in thread as read ───────────────

    public static function markThreadRead(int $threadId, int $readerId): void
    {
        db()->query(
            "UPDATE messages
             SET is_read = 1, read_at = NOW()
             WHERE thread_id = ? AND sender_id != ? AND is_read = 0",
            [$threadId, $readerId]
        );
    }

    // ── Unread count for a user ───────────────────────────

    public static function unreadCount(int $userId, string $role): int
    {
        if ($role === 'admin') {
            return (int) db()->fetchColumn(
                "SELECT COALESCE(SUM(unread_admin), 0)
                 FROM message_threads WHERE status = 'open'"
            );
        }

        return (int) db()->fetchColumn(
            "SELECT COALESCE(SUM(unread_investor), 0)
             FROM message_threads
             WHERE investor_id = ? AND status = 'open'",
            [$userId]
        );
    }
}