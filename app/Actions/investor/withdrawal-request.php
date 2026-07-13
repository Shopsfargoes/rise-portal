<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Withdrawal Request
// POST only. Called by investor/wallet.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\WalletBalance;
use Rise\Models\WalletTransaction;

if (!isPost()) redirect('/investor/wallet.php');
Auth::requireInvestor();
verifyCsrf();

$userId  = Auth::id();
$amount  = post('amount');
$note    = trim(post('note'));
$balance = WalletBalance::get($userId);

// ── Validate ──────────────────────────────────────────────
$errors = [];

if (!isPositiveNumber($amount))         $errors[] = 'Please enter a valid amount.';
if ((float)$amount < 100)               $errors[] = 'Minimum withdrawal amount is $100.';
if ((float)$amount > $balance)          $errors[] = 'Withdrawal amount exceeds your available balance of ' . formatMoney($balance) . '.';

// Check no other pending withdrawal
$existing = db()->fetchOne(
    "SELECT id FROM wallet_transactions
     WHERE user_id = ? AND type = 'withdrawal' AND status IN ('pending','contacted')
     LIMIT 1",
    [$userId]
);

if ($existing) $errors[] = 'You already have a pending withdrawal request.';

if (!empty($errors)) {
    flash(implode(' ', $errors), 'error');
    redirect('/investor/wallet.php');
}

// ── Create transaction + message thread ───────────────────
try {
    db()->transaction(function() use ($userId, $amount, $note, $balance) {
        // 1. Create wallet transaction
        $txId = WalletTransaction::create([
            'user_id' => $userId,
            'type'    => 'withdrawal',
            'amount'  => (float) $amount,
            'note'    => $note,
        ]);

        // 2. Auto-create a message thread
        $subject  = 'Withdrawal Request — ' . formatMoney((float)$amount);

        $threadId = db()->insert('message_threads', [
            'uuid'            => uuid4(),
            'investor_id'     => $userId,
            'context_type'    => 'transaction',
            'context_id'      => (int) $txId,
            'subject'         => $subject,
            'status'          => 'open',
            'unread_admin'    => 1,
            'unread_investor' => 0,
            'last_message_at' => date('Y-m-d H:i:s'),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        // 3. Auto first message
        $body = "I would like to withdraw " . formatMoney((float)$amount) . " from my account.\n\nCurrent balance: " . formatMoney($balance);
        if ($note) $body .= "\n\nNote: {$note}";

        db()->insert('messages', [
            'uuid'       => uuid4(),
            'thread_id'  => (int) $threadId,
            'sender_id'  => $userId,
            'body'       => $body,
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // 4. Notify all admins
        $admins = db()->fetchAll(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active'"
        );

        foreach ($admins as $admin) {
            db()->insert('notifications', [
                'user_id'      => $admin['id'],
                'type'         => 'withdrawal_request',
                'title'        => 'New Withdrawal Request',
                'message'      => Auth::name() . ' has requested a withdrawal of ' . formatMoney((float)$amount),
                'related_type' => 'wallet_transaction',
                'related_id'   => (int) $txId,
                'is_read'      => 0,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        // 5. Audit
        Auth::audit($userId, 'withdrawal_request', 'wallet_transaction', (int)$txId, [
            'amount'  => $amount,
            'balance' => $balance,
        ]);
    });

    flash(
        'Your withdrawal request for ' . formatMoney((float)$amount) .
        ' has been submitted. Our team will contact you to arrange payment.',
        'success'
    );

} catch (\Throwable $e) {
    error_log('withdrawal-request error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
}

redirect('/investor/wallet.php');