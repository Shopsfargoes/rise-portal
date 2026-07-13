<?php
// ============================================================
// RISE CAPITAL GROUP — Post Model
// ============================================================

namespace Rise\Models;

class Post
{
    // ── Fetch all posts ───────────────────────────────────

    public static function findAll(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[]  = "p.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[]  = "p.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['project_id'])) {
            $where[]  = "p.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['search'])) {
            $where[]  = "(p.title LIKE ? OR p.body LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT p.*,
                    pr.title      AS project_title,
                    u.email       AS author_email,
                    up.first_name AS author_first,
                    up.last_name  AS author_last
             FROM posts p
             LEFT JOIN projects pr ON pr.id = p.project_id
             LEFT JOIN users u ON u.id = p.author_id
             LEFT JOIN user_profiles up ON up.user_id = p.author_id
             WHERE {$whereSQL}
             ORDER BY p.published_at DESC, p.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ── Count ─────────────────────────────────────────────

    public static function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['type']))   { $where[] = "p.type = ?";   $params[] = $filters['type']; }
        if (!empty($filters['status'])) { $where[] = "p.status = ?"; $params[] = $filters['status']; }

        $whereSQL = implode(' AND ', $where);

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM posts p WHERE {$whereSQL}", $params
        );
    }

    // ── Find single post ──────────────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne(
            "SELECT p.*, pr.title AS project_title
             FROM posts p
             LEFT JOIN projects pr ON pr.id = p.project_id
             WHERE p.id = ? LIMIT 1",
            [$id]
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return db()->fetchOne(
            "SELECT p.*, pr.title AS project_title,
                    u.email AS author_email,
                    up.first_name AS author_first, up.last_name AS author_last
             FROM posts p
             LEFT JOIN projects pr ON pr.id = p.project_id
             LEFT JOIN users u ON u.id = p.author_id
             LEFT JOIN user_profiles up ON up.user_id = p.author_id
             WHERE p.slug = ? AND p.status = 'published' LIMIT 1",
            [$slug]
        );
    }

    // ── Create ────────────────────────────────────────────

    public static function create(array $data): int|string
    {
        $now = date('Y-m-d H:i:s');

        return db()->insert('posts', [
            'uuid'         => uuid4(),
            'project_id'   => $data['project_id']  ?? null,
            'author_id'    => $data['author_id'],
            'type'         => $data['type']         ?? 'news',
            'title'        => $data['title'],
            'slug'         => self::uniqueSlug($data['title']),
            'body'         => $data['body'],
            'cover_image'  => $data['cover_image']  ?? null,
            'status'       => $data['status']        ?? 'draft',
            'published_at' => ($data['status'] === 'published') ? $now : null,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    // ── Update ────────────────────────────────────────────

    public static function update(int $id, array $data): int
    {
        $now = date('Y-m-d H:i:s');

        // Set published_at when first publishing
        if (($data['status'] ?? '') === 'published') {
            $current = self::findById($id);
            if ($current && $current['status'] !== 'published') {
                $data['published_at'] = $now;
            }
        }

        $data['updated_at'] = $now;
        unset($data['slug'], $data['uuid'], $data['created_at']);

        return db()->update('posts', $data, ['id' => $id]);
    }

    // ── Delete ────────────────────────────────────────────

    public static function delete(int $id): void
    {
        db()->delete('posts', ['id' => $id]);
    }

    // ── Slug helper ───────────────────────────────────────

    private static function uniqueSlug(string $title): string
    {
        $base = slugify($title);
        $slug = $base;
        $i    = 1;

        while (db()->fetchColumn("SELECT id FROM posts WHERE slug = ? LIMIT 1", [$slug])) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}