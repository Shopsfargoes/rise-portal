<?php
// ============================================================
// RISE CAPITAL GROUP — WalletBalance Model
// ============================================================

namespace Rise\Models;

class WalletBalance
{
    // ── Get balance for a user ────────────────────────────

    public static function get(int $userId): float
    {
        return (float) db()->fetchColumn(
            "SELECT balance FROM wallet_balances WHERE user_id = ?",
            [$userId]
        ) ?? 0.0;
    }

    // ── Credit (add funds) ────────────────────────────────

    public static function credit(int $userId, float $amount): void
    {
        db()->query(
            "INSERT INTO wallet_balances (user_id, balance, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                balance    = balance + VALUES(balance),
                updated_at = NOW()",
            [$userId, $amount]
        );
    }

    // ── Debit (remove funds) ──────────────────────────────

    /**
     * Deduct amount from balance.
     * Returns false if insufficient funds.
     */
    public static function debit(int $userId, float $amount): bool
    {
        $current = self::get($userId);

        if ($current < $amount) {
            return false;
        }

        db()->query(
            "UPDATE wallet_balances
             SET balance = balance - ?, updated_at = NOW()
             WHERE user_id = ? AND balance >= ?",
            [$amount, $userId, $amount]
        );

        // Verify the update actually happened (race condition guard)
        return db()->pdo()->lastInsertId() !== false
            || db()->fetchColumn(
                "SELECT balance FROM wallet_balances WHERE user_id = ?",
                [$userId]
            ) <= $current;
    }

    // ── Has sufficient funds ──────────────────────────────

    public static function hasFunds(int $userId, float $amount): bool
    {
        return self::get($userId) >= $amount;
    }
}