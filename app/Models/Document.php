<?php
// ============================================================
// RISE CAPITAL GROUP — Document Model
// ============================================================

namespace Rise\Models;

class Document
{
    // ── Fetch all documents (admin) ───────────────────────

    public static function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(d.title LIKE ? OR d.file_name LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['project_id'])) {
            $where[]  = "d.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[]  = "d.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['visibility'])) {
            $where[]  = "d.visibility = ?";
            $params[] = $filters['visibility'];
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT d.*,
                    dc.name AS category_name,
                    p.title AS project_title,
                    u.email AS uploaded_by_email,
                    up.first_name AS uploaded_by_first,
                    up.last_name  AS uploaded_by_last
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             LEFT JOIN projects p ON p.id = d.project_id
             LEFT JOIN users u ON u.id = d.uploaded_by
             LEFT JOIN user_profiles up ON up.user_id = d.uploaded_by
             WHERE {$whereSQL}
             ORDER BY d.created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    // ── Count ─────────────────────────────────────────────

    public static function count(array $filters = []): int
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[]  = "(d.title LIKE ? OR d.file_name LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($filters['project_id'])) {
            $where[]  = "d.project_id = ?";
            $params[] = $filters['project_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[]  = "d.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['visibility'])) {
            $where[]  = "d.visibility = ?";
            $params[] = $filters['visibility'];
        }

        return (int) db()->fetchColumn(
            "SELECT COUNT(*) FROM documents d WHERE {$whereSQL}",
            $params
        );
    }

    // ── Find single document ──────────────────────────────

    public static function findById(int $id): ?array
    {
        return db()->fetchOne(
            "SELECT d.*, dc.name AS category_name, p.title AS project_title
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             LEFT JOIN projects p ON p.id = d.project_id
             WHERE d.id = ? LIMIT 1",
            [$id]
        );
    }

    public static function findByUuid(string $uuid): ?array
    {
        return db()->fetchOne(
            "SELECT d.*, dc.name AS category_name, p.title AS project_title
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             LEFT JOIN projects p ON p.id = d.project_id
             WHERE d.uuid = ? LIMIT 1",
            [$uuid]
        );
    }

    // ── Investor-accessible documents ─────────────────────

    /**
     * Get documents visible to investors, optionally filtered by project.
     * Groups by category for display.
     */
    public static function findForInvestor(?int $projectId = null): array
    {
        $where  = ["d.visibility IN ('investor','public')"];
        $params = [];

        if ($projectId) {
            $where[]  = "d.project_id = ?";
            $params[] = $projectId;
        }

        $whereSQL = implode(' AND ', $where);

        return db()->fetchAll(
            "SELECT d.*,
                    dc.name AS category_name,
                    p.title AS project_title
             FROM documents d
             LEFT JOIN document_categories dc ON dc.id = d.category_id
             LEFT JOIN projects p ON p.id = d.project_id
             WHERE {$whereSQL}
             ORDER BY dc.name ASC, d.created_at DESC",
            $params
        );
    }

    // ── Create ────────────────────────────────────────────

    public static function create(array $data): int|string
    {
        return db()->insert('documents', [
            'uuid'        => uuid4(),
            'project_id'  => $data['project_id']  ?? null,
            'category_id' => $data['category_id'] ?? null,
            'uploaded_by' => $data['uploaded_by'],
            'title'       => $data['title'],
            'file_name'   => $data['file_name'],
            'file_path'   => $data['file_path'],
            'file_size'   => $data['file_size']   ?? 0,
            'mime_type'   => $data['mime_type'],
            'visibility'  => $data['visibility']  ?? 'investor',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Delete ────────────────────────────────────────────

    public static function delete(int $id): bool
    {
        $doc = self::findById($id);
        if (!$doc) return false;

        // Delete physical file
        $filePath = STORAGE_PATH . '/' . $doc['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        db()->delete('documents', ['id' => $id]);
        return true;
    }

    // ── Increment download counter ────────────────────────

    public static function incrementDownload(int $id): void
    {
        db()->query(
            "UPDATE documents SET download_count = download_count + 1 WHERE id = ?",
            [$id]
        );
    }

    // ── Categories ────────────────────────────────────────

    public static function getCategories(): array
    {
        return db()->fetchAll(
            "SELECT * FROM document_categories ORDER BY name ASC"
        );
    }

    // ── Helpers ───────────────────────────────────────────

    public static function visibilityLabel(string $visibility): string
    {
        return match($visibility) {
            'admin'    => 'Admin Only',
            'investor' => 'All Investors',
            'public'   => 'Public',
            default    => ucfirst($visibility),
        };
    }

    public static function visibilityBadge(string $visibility): string
    {
        return match($visibility) {
            'admin'    => 'badge-red',
            'investor' => 'badge-blue',
            'public'   => 'badge-green',
            default    => 'badge-grey',
        };
    }

    public static function mimeIcon(string $mimeType): string
    {
        return match(true) {
            str_contains($mimeType, 'pdf')        => '📄',
            str_contains($mimeType, 'word')       => '📝',
            str_contains($mimeType, 'excel')
                || str_contains($mimeType, 'spreadsheet') => '📊',
            str_contains($mimeType, 'image')      => '🖼',
            default                               => '📎',
        };
    }
}