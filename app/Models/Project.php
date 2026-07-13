<?php
// ============================================================
// RISE CAPITAL GROUP — Project Model
// ============================================================

namespace Rise\Models;

class Project
{
    // ── Fetch all projects (with filters) ─────────────────

    public static function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(p.title LIKE ? OR p.location LIKE ? OR p.description LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $where[]  = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['category_id'])) {
            $where[]  = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT p.*,
                    pc.name AS category_name,
                    COUNT(DISTINCT i.id) AS investor_count,
                    COALESCE(SUM(i.amount), 0) AS total_raised
             FROM projects p
             LEFT JOIN project_categories pc ON pc.id = p.category_id
             LEFT JOIN investments i ON i.project_id = p.id AND i.status = 'active'
             WHERE {$whereSQL}
             GROUP BY p.id
             ORDER BY p.sort_order ASC, p.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ── Count for pagination ───────────────────────────────

    public static function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(p.title LIKE ? OR p.location LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['status'])) {
            $where[]  = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['category_id'])) {
            $where[]  = "p.category_id = ?";
            $params[] = $filters['category_id'];
        }

        $whereSQL = implode(' AND ', $where);

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM projects p WHERE {$whereSQL}",
            $params
        );
    }

    // ── Find single project ────────────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne(
            "SELECT p.*, pc.name AS category_name
             FROM projects p
             LEFT JOIN project_categories pc ON pc.id = p.category_id
             WHERE p.id = ? LIMIT 1",
            [$id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return db()->fetchOne(
            "SELECT p.*, pc.name AS category_name
             FROM projects p
             LEFT JOIN project_categories pc ON pc.id = p.category_id
             WHERE p.slug = ? LIMIT 1",
            [$slug]
        );
    }

    // ── Tags ──────────────────────────────────────────────

    public static function getTags(int $projectId): array
    {
        return db()->fetchAll(
            "SELECT tag FROM project_tags WHERE project_id = ? ORDER BY id ASC",
            [$projectId]
        );
    }

    public static function syncTags(int $projectId, array $tags): void
    {
        db()->delete('project_tags', ['project_id' => $projectId]);

        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag === '') continue;
            db()->insert('project_tags', [
                'project_id' => $projectId,
                'tag'        => $tag,
            ]);
        }
    }

    // ── Timeline ──────────────────────────────────────────

    public static function getTimeline(int $projectId): array
    {
        return db()->fetchAll(
            "SELECT * FROM project_timeline
             WHERE project_id = ?
             ORDER BY event_date ASC, sort_order ASC",
            [$projectId]
        );
    }

    public static function addTimelineEvent(int $projectId, array $data): int|string
    {
        return db()->insert('project_timeline', [
            'project_id'  => $projectId,
            'event_date'  => $data['event_date'],
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public static function deleteTimelineEvent(int $eventId, int $projectId): int
    {
        return db()->delete('project_timeline', [
            'id'         => $eventId,
            'project_id' => $projectId,  // ensure ownership
        ]);
    }

    // ── Create ────────────────────────────────────────────

    public static function create(array $data): int|string
    {
        $now = date('Y-m-d H:i:s');

        return db()->insert('projects', [
            'uuid'            => uuid4(),
            'category_id'     => $data['category_id']     ?? null,
            'title'           => $data['title'],
            'slug'            => self::uniqueSlug($data['title']),
            'location'        => $data['location']        ?? null,
            'status'          => $data['status']          ?? 'open',
            'project_type'    => $data['project_type']    ?? null,
            'production_type' => $data['production_type'] ?? null,
            'target_depth'    => $data['target_depth']    ?? null,
            'project_cost'    => $data['project_cost']    ?? 0,
            'total_raised'    => 0,
            'description'     => $data['description']     ?? null,
            'cover_image'     => $data['cover_image']     ?? null,
            'map_lat'         => $data['map_lat']         ?? null,
            'map_lng'         => $data['map_lng']         ?? null,
            'is_featured'     => $data['is_featured']     ?? 0,
            'sort_order'      => $data['sort_order']      ?? 0,
            'created_by'      => $data['created_by'],
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }

    // ── Update ────────────────────────────────────────────

    public static function update(int $id, array $data): int
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Don't let slug be updated accidentally
        unset($data['slug'], $data['uuid'], $data['created_at'], $data['total_raised']);

        return db()->update('projects', $data, ['id' => $id]);
    }

    // ── Delete ────────────────────────────────────────────

    public static function delete(int $id): int
    {
        // Tags and timeline cascade via FK
        return db()->delete('projects', ['id' => $id]);
    }

    // ── Categories ────────────────────────────────────────

    public static function getCategories(): array
    {
        return db()->fetchAll("SELECT * FROM project_categories ORDER BY name ASC");
    }

    // ── Stats for a single project ────────────────────────

    public static function getStats(int $projectId): array
    {
        return db()->fetchOne(
            "SELECT
                COUNT(DISTINCT i.user_id)    AS investor_count,
                COALESCE(SUM(i.amount), 0)   AS total_raised,
                COALESCE(SUM(d.total_amount), 0) AS total_distributed
             FROM projects p
             LEFT JOIN investments i ON i.project_id = p.id AND i.status = 'active'
             LEFT JOIN distributions d ON d.project_id = p.id
             WHERE p.id = ?",
            [$projectId]
        ) ?? [];
    }

    // ── Helpers ───────────────────────────────────────────

    private static function uniqueSlug(string $title): string
    {
        $base = slugify($title);
        $slug = $base;
        $i    = 1;

        while (db()->fetchColumn("SELECT id FROM projects WHERE slug = ? LIMIT 1", [$slug])) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    // ── Status label helpers ───────────────────────────────

    public static function statusBadgeClass(string $status): string
    {
        return match($status) {
            'open'      => 'badge-green',
            'closed'    => 'badge-grey',
            'drilled'   => 'badge-gold',
            'producing' => 'badge-blue',
            'abandoned' => 'badge-red',
            default     => 'badge-grey',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match($status) {
            'open'      => 'Open',
            'closed'    => 'Closed',
            'drilled'   => 'Drilled',
            'producing' => 'Producing',
            'abandoned' => 'Abandoned',
            default     => ucfirst($status),
        };
    }
}