<?php
// ============================================================
// RISE CAPITAL GROUP — Media Model
// ============================================================

namespace Rise\Models;

class Media
{
    // ── Fetch all media ───────────────────────────────────

    public static function findAll(array $filters = [], int $limit = 40, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[]  = "m.type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['project_id'])) {
            $where[]  = "m.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['search'])) {
            $where[]  = "(m.title LIKE ? OR m.file_name LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT m.*,
                    p.title AS project_title,
                    u.email AS uploaded_by_email,
                    up.first_name AS uploader_first,
                    up.last_name  AS uploader_last
             FROM media m
             LEFT JOIN projects p ON p.id = m.project_id
             LEFT JOIN users u ON u.id = m.uploaded_by
             LEFT JOIN user_profiles up ON up.user_id = m.uploaded_by
             WHERE {$whereSQL}
             ORDER BY m.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ── Count ─────────────────────────────────────────────

    public static function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['type'])) {
            $where[]  = "m.type = ?";
            $params[] = $filters['type'];
        }

        $whereSQL = implode(' AND ', $where);

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM media m WHERE {$whereSQL}", $params
        );
    }

    // ── Find by ID ────────────────────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne("SELECT * FROM media WHERE id = ? LIMIT 1", [$id]);
    }

    // ── Create ────────────────────────────────────────────

    public static function create(array $data): int|string
    {
        return db()->insert('media', [
            'uuid'        => uuid4(),
            'uploaded_by' => $data['uploaded_by'],
            'project_id'  => $data['project_id'] ?? null,
            'type'        => $data['type']        ?? 'image',
            'title'       => $data['title']       ?? null,
            'file_name'   => $data['file_name'],
            'file_path'   => $data['file_path'],
            'file_size'   => $data['file_size']   ?? 0,
            'mime_type'   => $data['mime_type'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Delete ────────────────────────────────────────────

    public static function delete(int $id): void
    {
        $media = self::findById($id);
        if (!$media) return;

        // Delete physical file
        $path = PUBLIC_PATH . '/assets/uploads/media/' . $media['file_name'];
        if (file_exists($path)) unlink($path);

        db()->delete('media', ['id' => $id]);
    }

    // ── Type icon ─────────────────────────────────────────

    public static function icon(string $type): string
    {
        return match($type) {
            'image'    => '🖼',
            'video'    => '🎥',
            'document' => '📄',
            default    => '📎',
        };
    }

    // ── Mime to type ──────────────────────────────────────

    public static function mimeToType(string $mime): string
    {
        return match(true) {
            str_contains($mime, 'image') => 'image',
            str_contains($mime, 'video') => 'video',
            str_contains($mime, 'pdf')
            || str_contains($mime, 'word')
            || str_contains($mime, 'excel') => 'document',
            default => 'other',
        };
    }
}