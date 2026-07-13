<?php
// ============================================================
// RISE CAPITAL GROUP — WalletTransaction Model
// ============================================================

namespace Rise\Models;

class WalletTransaction
{
    // ── Fetch all transactions ─────────────────────────────

    public static function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]  = "wt.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['type'])) {
            $where[]  = "wt.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[]  = "wt.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]  = "(up.first_name LIKE ? OR up.last_name LIKE ? OR u.email LIKE ? OR wt.reference LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT wt.*,
                    u.email        AS investor_email,
                    up.first_name  AS investor_first,
                    up.last_name   AS investor_last,
                    up.avatar_path,
                    adm.email      AS admin_email,
                    admp.first_name AS admin_first,
                    admp.last_name  AS admin_last
             FROM wallet_transactions wt
             JOIN users u ON u.id = wt.user_id
             LEFT JOIN user_profiles up ON up.user_id = wt.user_id
             LEFT JOIN users adm ON adm.id = wt.admin_id
             LEFT JOIN user_profiles admp ON admp.user_id = wt.admin_id
             WHERE {$whereSQL}
             ORDER BY wt.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ── Count ─────────────────────────────────────────────

    public static function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]  = "wt.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['type'])) {
            $where[]  = "wt.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[]  = "wt.status = ?";
            $params[] = $filters['status'];
        }

        $whereSQL = implode(' AND ', $where);

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM wallet_transactions wt WHERE {$whereSQL}",
            $params
        );
    }

    // ── Find single transaction ────────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne(
            "SELECT wt.*,
                    u.email       AS investor_email,
                    up.first_name AS investor_first,
                    up.last_name  AS investor_last
             FROM wallet_transactions wt
             JOIN users u ON u.id = wt.user_id
             LEFT JOIN user_profiles up ON up.user_id = wt.user_id
             WHERE wt.id = ? LIMIT 1",
            [$id]
        );
    }

    // ── Pending requests (admin dashboard) ────────────────

    public static function getPending(): array
    {
        return db()->fetchAll(
            "SELECT wt.*,
                    u.email       AS investor_email,
                    up.first_name AS investor_first,
                    up.last_name  AS investor_last,
                    up.avatar_path,
                    COALESCE(wb.balance, 0) AS investor_balance
             FROM wallet_transactions wt
             JOIN users u ON u.id = wt.user_id
             LEFT JOIN user_profiles up ON up.user_id = wt.user_id
             LEFT JOIN wallet_balances wb ON wb.user_id = wt.user_id
             WHERE wt.status IN ('pending','contacted')
             ORDER BY wt.created_at ASC"
        );
    }

    // ── Create ────────────────────────────────────────────

    public static function create(array $data): int|string
    {
        return db()->insert('wallet_transactions', [
            'uuid'       => uuid4(),
            'user_id'    => $data['user_id'],
            'type'       => $data['type'],
            'amount'     => (float) $data['amount'],
            'currency'   => $data['currency']  ?? 'USD',
            'note'       => $data['note']       ?? null,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Update status ─────────────────────────────────────

    public static function updateStatus(
        int    $id,
        string $status,
        int    $adminId,
        string $adminNote = '',
        string $reference = ''
    ): void {
        $data = [
            'status'     => $status,
            'admin_id'   => $adminId,
            'admin_note' => $adminNote ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($status === 'confirmed') {
            $data['confirmed_at'] = date('Y-m-d H:i:s');
            if ($reference) $data['reference'] = $reference;
        }

        if ($status === 'rejected') {
            $data['rejected_at'] = date('Y-m-d H:i:s');
        }

        db()->update('wallet_transactions', $data, ['id' => $id]);
    }

    // ── Status helpers ────────────────────────────────────

    public static function statusBadge(string $status): string
    {
        return match($status) {
            'pending'   => 'badge-orange',
            'contacted' => 'badge-blue',
            'confirmed' => 'badge-green',
            'rejected'  => 'badge-red',
            default     => 'badge-grey',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match($status) {
            'pending'   => 'Pending',
            'contacted' => 'Contacted',
            'confirmed' => 'Confirmed',
            'rejected'  => 'Rejected',
            default     => ucfirst($status),
        };
    }
}