<?php
// ============================================================
// RISE CAPITAL GROUP — Action: Deposit Request
// POST only. Called by investor/wallet.php
// ============================================================
require_once __DIR__ . '/../../bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Mailer;
use Rise\Models\WalletTransaction;

if (!isPost()) redirect('/investor/wallet.php');
Auth::requireInvestor();
verifyCsrf();

$userId = Auth::id();
$amount = post('amount');
$note   = trim(post('note'));

// ── Validate ──────────────────────────────────────────────
$errors = [];

if (!isPositiveNumber($amount))      $errors[] = 'Please enter a valid amount.';
if ((float)$amount < 1000)           $errors[] = 'Minimum deposit amount is $1,000.';

// Check no other pending deposit
$existing = db()->fetchOne(
    "SELECT id FROM wallet_transactions
     WHERE user_id = ? AND type = 'deposit' AND status IN ('pending','contacted')
     LIMIT 1",
    [$userId]
);

if ($existing) $errors[] = 'You already have a pending deposit request. Please wait for it to be processed.';

if (!empty($errors)) {
    flash(implode(' ', $errors), 'error');
    redirect('/investor/wallet.php');
}

// ── Create transaction + message thread ───────────────────
try {
    $txId = db()->transaction(function() use ($userId, $amount, $note) {
        // 1. Create wallet transaction
        $txId = WalletTransaction::create([
            'user_id' => $userId,
            'type'    => 'deposit',
            'amount'  => (float) $amount,
            'note'    => $note,
        ]);

        // 2. Auto-create a message thread tied to this transaction
        $subject = 'Deposit Request — ' . formatMoney((float)$amount);

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

        // 3. Auto first message in thread
        $body = "I would like to deposit " . formatMoney((float)$amount) . " into my account.";
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
            "SELECT u.id FROM users u WHERE u.role = 'admin' AND u.status = 'active'"
        );

        foreach ($admins as $admin) {
            db()->insert('notifications', [
                'user_id'      => $admin['id'],
                'type'         => 'deposit_request',
                'title'        => 'New Deposit Request',
                'message'      => Auth::name() . ' has requested a deposit of ' . formatMoney((float)$amount),
                'related_type' => 'wallet_transaction',
                'related_id'   => (int) $txId,
                'is_read'      => 0,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        // 5. Audit
        Auth::audit($userId, 'deposit_request', 'wallet_transaction', (int)$txId, [
            'amount' => $amount,
        ]);

        return $txId;
    });

    // Send confirmation email to investor
    $user = Auth::user();
    Mailer::sendDepositReceived(
        $user['email'],
        $user['first_name'] . ' ' . $user['last_name'],
        (float) $amount
    );

    flash(
        'Your deposit request for ' . formatMoney((float)$amount) .
        ' has been submitted. Our team will contact you shortly with wire transfer instructions.',
        'success'
    );

} catch (\Throwable $e) {
    error_log('deposit-request error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
}

redirect('/investor/wallet.php');