<?php
// ============================================================
// RISE CAPITAL GROUP — Distribution Model
// ============================================================

namespace Rise\Models;

class Distribution
{
    // ── Fetch all distributions (admin) ───────────────────

    public static function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['project_id'])) {
            $where[]  = "d.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['search'])) {
            $where[]  = "(p.title LIKE ? OR d.description LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT d.*,
                    p.title    AS project_title,
                    p.location AS project_location,
                    COUNT(di.id)            AS recipient_count,
                    SUM(di.amount)          AS items_total,
                    SUM(CASE WHEN di.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    u.email                 AS created_by_email,
                    up.first_name           AS created_by_first,
                    up.last_name            AS created_by_last
             FROM distributions d
             JOIN projects p ON p.id = d.project_id
             LEFT JOIN distribution_items di ON di.distribution_id = d.id
             LEFT JOIN users u ON u.id = d.created_by
             LEFT JOIN user_profiles up ON up.user_id = d.created_by
             WHERE {$whereSQL}
             GROUP BY d.id
             ORDER BY d.distribution_date DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ── Count ─────────────────────────────────────────────

    public static function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['project_id'])) {
            $where[]  = "d.project_id = ?";
            $params[] = $filters['project_id'];
        }

        $whereSQL = implode(' AND ', $where);

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM distributions d WHERE {$whereSQL}",
            $params
        );
    }

    // ── Find single distribution ──────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne(
            "SELECT d.*, p.title AS project_title
             FROM distributions d
             JOIN projects p ON p.id = d.project_id
             WHERE d.id = ? LIMIT 1",
            [$id]
        );
    }

    // ── Get items for a distribution ──────────────────────

    public static function getItems(int $distributionId): array
    {
        return db()->fetchAll(
            "SELECT di.*,
                    u.email       AS investor_email,
                    up.first_name AS investor_first,
                    up.last_name  AS investor_last,
                    up.avatar_path,
                    i.amount      AS investment_amount
             FROM distribution_items di
             JOIN users u ON u.id = di.user_id
             LEFT JOIN user_profiles up ON up.user_id = di.user_id
             LEFT JOIN investments i ON i.id = di.investment_id
             WHERE di.distribution_id = ?
             ORDER BY di.amount DESC",
            [$distributionId]
        );
    }

    // ── Get distributions for an investor ─────────────────

    public static function findByUser(int $userId): array
    {
        return db()->fetchAll(
            "SELECT di.*,
                    d.distribution_date,
                    d.description AS dist_description,
                    d.total_amount,
                    p.title       AS project_title,
                    p.slug        AS project_slug,
                    p.location    AS project_location
             FROM distribution_items di
             JOIN distributions d ON d.id = di.distribution_id
             JOIN projects p ON p.id = d.project_id
             WHERE di.user_id = ?
             ORDER BY d.distribution_date DESC",
            [$userId]
        );
    }

    // ── Create distribution + auto-split items ────────────

    /**
     * Create a distribution and automatically calculate
     * each investor's share based on their % of total invested.
     *
     * @param array $data  [project_id, distribution_date, total_amount, description, created_by]
     * @return int  Distribution ID
     */
    public static function createWithAutoSplit(array $data): int
    {
        // Fetch all active investments for this project
        $investments = db()->fetchAll(
            "SELECT i.id, i.user_id, i.amount
             FROM investments i
             WHERE i.project_id = ? AND i.status = 'active'",
            [$data['project_id']]
        );

        if (empty($investments)) {
            throw new \RuntimeException('No active investments found for this project.');
        }

        $totalInvested = array_sum(array_column($investments, 'amount'));

        if ($totalInvested <= 0) {
            throw new \RuntimeException('Total invested amount is zero — cannot calculate shares.');
        }

        $distributionId = db()->transaction(function() use ($data, $investments, $totalInvested) {
            $now = date('Y-m-d H:i:s');

            // 1. Insert distribution header
            $distId = db()->insert('distributions', [
                'uuid'              => uuid4(),
                'project_id'        => $data['project_id'],
                'distribution_date' => $data['distribution_date'],
                'total_amount'      => (float) $data['total_amount'],
                'description'       => $data['description'] ?? null,
                'created_by'        => $data['created_by'],
                'created_at'        => $now,
            ]);

            // 2. Calculate and insert per-investor items
            $runningTotal = 0;
            $lastIndex    = count($investments) - 1;

            foreach ($investments as $index => $inv) {
                // Calculate share — give remainder to last investor to avoid rounding gaps
                if ($index === $lastIndex) {
                    $share = round((float)$data['total_amount'] - $runningTotal, 2);
                } else {
                    $share = round(((float)$inv['amount'] / $totalInvested) * (float)$data['total_amount'], 2);
                    $runningTotal += $share;
                }

                db()->insert('distribution_items', [
                    'distribution_id' => (int) $distId,
                    'user_id'         => $inv['user_id'],
                    'investment_id'   => $inv['id'],
                    'amount'          => $share,
                    'status'          => 'pending',
                ]);

                // Notify each investor
                db()->insert('notifications', [
                    'user_id'      => $inv['user_id'],
                    'type'         => 'distribution',
                    'title'        => 'New Distribution',
                    'message'      => 'You have received a distribution of ' .
                                     formatMoney($share) .
                                     ($data['description'] ? ' — ' . $data['description'] : ''),
                    'related_type' => 'distribution',
                    'related_id'   => (int) $distId,
                    'is_read'      => 0,
                    'created_at'   => $now,
                ]);
            }

            return (int) $distId;
        });

        return $distributionId;
    }

    // ── Mark item as paid ─────────────────────────────────

    public static function markItemPaid(int $itemId): void
    {
        db()->update('distribution_items', [
            'status'  => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ], ['id' => $itemId]);
    }

    // ── Mark all items in a distribution as paid ──────────

    public static function markAllPaid(int $distributionId): void
    {
        $now = date('Y-m-d H:i:s');
        db()->query(
            "UPDATE distribution_items
             SET status = 'paid', paid_at = ?
             WHERE distribution_id = ? AND status = 'pending'",
            [$now, $distributionId]
        );
    }

    // ── Summary stats ─────────────────────────────────────

    public static function companySummary(): array
    {
        return db()->fetchOne(
            "SELECT
                COUNT(DISTINCT d.id)              AS total_distributions,
                COALESCE(SUM(d.total_amount), 0)  AS total_distributed,
                COUNT(DISTINCT d.project_id)      AS projects_with_distributions,
                COALESCE(SUM(CASE WHEN di.status = 'pending' THEN di.amount END), 0) AS pending_amount
             FROM distributions d
             LEFT JOIN distribution_items di ON di.distribution_id = d.id"
        ) ?? [];
    }
}