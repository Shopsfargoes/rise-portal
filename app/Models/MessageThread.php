<?php
// ============================================================
// RISE CAPITAL GROUP — MessageThread Model
// ============================================================

namespace Rise\Models;

class MessageThread
{
    // ── Find all threads (admin — all investors) ──────────

    public static function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['investor_id'])) {
            $where[]  = "mt.investor_id = ?";
            $params[] = $filters['investor_id'];
        }

        if (!empty($filters['status'])) {
            $where[]  = "mt.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['context_type'])) {
            $where[]  = "mt.context_type = ?";
            $params[] = $filters['context_type'];
        }

        if (!empty($filters['search'])) {
            $where[]  = "(mt.subject LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR u.email LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT mt.*,
                    u.email       AS investor_email,
                    up.first_name AS investor_first,
                    up.last_name  AS investor_last,
                    up.avatar_path,
                    (SELECT body FROM messages m
                     WHERE m.thread_id = mt.id
                     ORDER BY m.created_at DESC LIMIT 1) AS last_message
             FROM message_threads mt
             JOIN users u ON u.id = mt.investor_id
             LEFT JOIN user_profiles up ON up.user_id = mt.investor_id
             WHERE {$whereSQL}
             ORDER BY mt.last_message_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ── Find all threads for a specific investor ──────────

    public static function findByInvestor(int $investorId): array
    {
        return db()->fetchAll(
            "SELECT mt.*,
                    (SELECT body FROM messages m
                     WHERE m.thread_id = mt.id
                     ORDER BY m.created_at DESC LIMIT 1) AS last_message
             FROM message_threads mt
             WHERE mt.investor_id = ?
             ORDER BY mt.last_message_at DESC",
            [$investorId]
        );
    }

    // ── Find single thread ────────────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne(
            "SELECT mt.*,
                    u.email       AS investor_email,
                    up.first_name AS investor_first,
                    up.last_name  AS investor_last,
                    up.avatar_path
             FROM message_threads mt
             JOIN users u ON u.id = mt.investor_id
             LEFT JOIN user_profiles up ON up.user_id = mt.investor_id
             WHERE mt.id = ? LIMIT 1",
            [$id]
        );
    }

    // ── Find thread by context ────────────────────────────

    public static function findByContext(string $contextType, int $contextId): ?array
    {
        return db()->fetchOne(
            "SELECT * FROM message_threads
             WHERE context_type = ? AND context_id = ? LIMIT 1",
            [$contextType, $contextId]
        );
    }

    // ── Create a new thread ───────────────────────────────

    public static function create(array $data): int|string
    {
        $now = date('Y-m-d H:i:s');

        return db()->insert('message_threads', [
            'uuid'            => uuid4(),
            'investor_id'     => $data['investor_id'],
            'context_type'    => $data['context_type']    ?? 'general',
            'context_id'      => $data['context_id']      ?? null,
            'subject'         => $data['subject']          ?? null,
            'status'          => 'open',
            'unread_admin'    => $data['unread_admin']     ?? 1,
            'unread_investor' => $data['unread_investor']  ?? 0,
            'last_message_at' => $now,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    // ── Increment unread counter ──────────────────────────

    public static function incrementUnread(int $threadId, string $side): void
    {
        $col = $side === 'admin' ? 'unread_admin' : 'unread_investor';
        db()->query(
            "UPDATE message_threads
             SET {$col} = {$col} + 1,
                 last_message_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?",
            [$threadId]
        );
    }

    // ── Reset unread counter ──────────────────────────────

    public static function resetUnread(int $threadId, string $side): void
    {
        $col = $side === 'admin' ? 'unread_admin' : 'unread_investor';
        db()->query(
            "UPDATE message_threads SET {$col} = 0, updated_at = NOW() WHERE id = ?",
            [$threadId]
        );
    }

    // ── Close thread ──────────────────────────────────────

    public static function close(int $threadId): void
    {
        db()->update('message_threads', [
            'status'     => 'closed',
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $threadId]);
    }

    // ── Count threads ─────────────────────────────────────

    public static function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['investor_id'])) {
            $where[]  = "mt.investor_id = ?";
            $params[] = $filters['investor_id'];
        }

        if (!empty($filters['status'])) {
            $where[]  = "mt.status = ?";
            $params[] = $filters['status'];
        }

        $whereSQL = implode(' AND ', $where);

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM message_threads mt WHERE {$whereSQL}",
            $params
        );
    }

    // ── Context label helper ──────────────────────────────

    public static function contextLabel(string $contextType, ?int $contextId): string
    {
        return match($contextType) {
            'transaction' => 'Wallet Transaction',
            'project'     => 'Project Enquiry',
            'general'     => 'General',
            default       => ucfirst($contextType),
        };
    }

    public static function contextBadge(string $contextType): string
    {
        return match($contextType) {
            'transaction' => 'badge-gold',
            'project'     => 'badge-blue',
            'general'     => 'badge-grey',
            default       => 'badge-grey',
        };
    }
}