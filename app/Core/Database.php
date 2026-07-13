<?php
// ============================================================
// RISE CAPITAL GROUP — Database
// PDO singleton with prepared statement helpers
// ============================================================

namespace Rise\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    // ── Constructor ───────────────────────────────────────────
    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose credentials in output
            if (APP_DEBUG) {
                die('Database connection failed: ' . $e->getMessage());
            }
            die('Database connection failed. Please try again later.');
        }
    }

    // ── Singleton access ──────────────────────────────────────
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() { throw new \Exception('Cannot unserialize singleton.'); }

    // ── Raw PDO access (escape hatch) ─────────────────────────
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // ── Query helpers ─────────────────────────────────────────

    /**
     * Run a query and return the PDOStatement.
     * Use for INSERT / UPDATE / DELETE where you need rowCount().
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row. Returns array|null.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    /**
     * Fetch all matching rows. Returns array (empty if none).
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single column value from the first row.
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->query($sql, $params);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : null;
    }

    /**
     * Insert a row and return the last insert ID.
     */
    public function insert(string $table, array $data): string|false
    {
        $columns     = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching $where conditions.
     * $where = ['id' => 5] → WHERE id = ?
     */
    public function update(string $table, array $data, array $where): int
    {
        $set   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $conds = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $sql   = "UPDATE `{$table}` SET {$set} WHERE {$conds}";
        $stmt  = $this->query($sql, [...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    /**
     * Delete rows matching $where conditions.
     */
    public function delete(string $table, array $where): int
    {
        $conds = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $sql   = "DELETE FROM `{$table}` WHERE {$conds}";
        return $this->query($sql, array_values($where))->rowCount();
    }

    // ── Transactions ──────────────────────────────────────────

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Wrap a callable in a DB transaction.
     * Rolls back automatically on exception and re-throws.
     *
     * Usage:
     *   $db->transaction(function() use ($db) {
     *       $db->insert(...);
     *       $db->update(...);
     *   });
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}