<?php
// ============================================================
// RISE CAPITAL GROUP — Investment Model
// ============================================================

namespace Rise\Models;

class Investment
{
    // ── Fetch all investments (admin) ─────────────────────

    public static function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]  = "i.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['project_id'])) {
            $where[]  = "i.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['status'])) {
            $where[]  = "i.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[]  = "(p.title LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR u.email LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT i.*,
                    p.title    AS project_title,
                    p.location AS project_location,
                    p.status   AS project_status,
                    u.email    AS investor_email,
                    up.first_name AS investor_first,
                    up.last_name  AS investor_last,
                    up.avatar_path
             FROM investments i
             JOIN projects p  ON p.id  = i.project_id
             JOIN users u     ON u.id  = i.user_id
             LEFT JOIN user_profiles up ON up.user_id = i.user_id
             WHERE {$whereSQL}
             ORDER BY i.invested_at DESC
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
            $where[]  = "i.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['project_id'])) {
            $where[]  = "i.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['status'])) {
            $where[]  = "i.status = ?";
            $params[] = $filters['status'];
        }

        $whereSQL = implode(' AND ', $where);

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM investments i
             JOIN projects p ON p.id = i.project_id
             WHERE {$whereSQL}",
            $params
        );
    }

    // ── Find by user (investor portfolio) ─────────────────

    public static function findByUser(int $userId): array
    {
        return db()->fetchAll(
            "SELECT i.*,
                    p.title           AS project_title,
                    p.location        AS project_location,
                    p.status          AS project_status,
                    p.slug            AS project_slug,
                    p.cover_image     AS project_cover,
                    p.production_type,
                    p.project_type,
                    COALESCE(SUM(di.amount), 0) AS total_distributed
             FROM investments i
             JOIN projects p ON p.id = i.project_id
             LEFT JOIN distribution_items di
                ON di.investment_id = i.id AND di.status = 'paid'
             WHERE i.user_id = ?
             GROUP BY i.id
             ORDER BY i.invested_at DESC",
            [$userId]
        );
    }

    // ── Summary stats for a user ──────────────────────────

    public static function summaryForUser(int $userId): array
    {
        return db()->fetchOne(
            "SELECT
                COUNT(DISTINCT i.id)              AS active_count,
                COALESCE(SUM(i.amount), 0)        AS total_invested,
                COALESCE(AVG(i.amount), 0)        AS avg_investment,
                COALESCE(SUM(di.amount), 0)       AS total_distributed
             FROM investments i
             LEFT JOIN distribution_items di
                ON di.investment_id = i.id AND di.status = 'paid'
             WHERE i.user_id = ? AND i.status = 'active'",
            [$userId]
        ) ?? [];
    }

    // ── Company-wide stats (admin dashboard) ──────────────

    public static function companyStats(): array
    {
        return db()->fetchOne(
            "SELECT
                COUNT(DISTINCT i.id)                AS active_investments,
                COUNT(DISTINCT i.user_id)           AS active_investors,
                COALESCE(SUM(i.amount), 0)          AS total_invested,
                COALESCE(AVG(i.amount), 0)          AS avg_investment,
                COALESCE(SUM(wb.balance), 0)        AS total_wallet_balance,
                COALESCE(SUM(di.amount), 0)         AS total_distributed
             FROM investments i
             LEFT JOIN wallet_balances wb ON wb.user_id = i.user_id
             LEFT JOIN distribution_items di
                ON di.investment_id = i.id AND di.status = 'paid'
             WHERE i.status = 'active'"
        ) ?? [];
    }

    // ── Create ────────────────────────────────────────────

    public static function create(array $data): int|string
    {
        $now = date('Y-m-d H:i:s');

        $investmentId = db()->insert('investments', [
            'uuid'        => uuid4(),
            'user_id'     => $data['user_id'],
            'project_id'  => $data['project_id'],
            'amount'      => (float) $data['amount'],
            'status'      => $data['status'] ?? 'active',
            'invested_at' => $data['invested_at'] ?? $now,
            'notes'       => $data['notes'] ?? null,
            'created_by'  => $data['created_by'],
        ]);

        // Update project total_raised
        db()->query(
            "UPDATE projects
             SET total_raised = (
                 SELECT COALESCE(SUM(amount), 0)
                 FROM investments
                 WHERE project_id = ? AND status = 'active'
             )
             WHERE id = ?",
            [$data['project_id'], $data['project_id']]
        );

        return $investmentId;
    }

    // ── Update status ─────────────────────────────────────

    public static function updateStatus(int $id, string $status): void
    {
        db()->update('investments', ['status' => $status], ['id' => $id]);

        // Refresh project total_raised
        $inv = db()->fetchOne("SELECT project_id FROM investments WHERE id = ?", [$id]);
        if ($inv) {
            db()->query(
                "UPDATE projects
                 SET total_raised = (
                     SELECT COALESCE(SUM(amount), 0)
                     FROM investments
                     WHERE project_id = ? AND status = 'active'
                 )
                 WHERE id = ?",
                [$inv['project_id'], $inv['project_id']]
            );
        }
    }

    // ── Find by ID ────────────────────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne(
            "SELECT i.*, p.title AS project_title, u.email AS investor_email,
                    up.first_name, up.last_name
             FROM investments i
             JOIN projects p ON p.id = i.project_id
             JOIN users u ON u.id = i.user_id
             LEFT JOIN user_profiles up ON up.user_id = i.user_id
             WHERE i.id = ? LIMIT 1",
            [$id]
        );
    }
}