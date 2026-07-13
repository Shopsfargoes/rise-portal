<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Wallet Confirm Action
// POST only. Handles contacted / confirmed / rejected
// Called by admin/wallet/pending.php
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Core\Mailer;
use Rise\Models\WalletTransaction;
use Rise\Models\WalletBalance;

if (!isPost()) redirect('/admin/wallet/pending.php');
Auth::requireAdmin();
verifyCsrf();

$txId      = (int) post('tx_id');
$action    = post('action');
$adminNote = trim(post('admin_note', ''));
$reference = trim(post('reference', ''));

if (!$txId || !in_array($action, ['contacted', 'confirmed', 'rejected'])) {
    flash('Invalid request.', 'error');
    redirect('/admin/wallet/pending.php');
}

// Fetch transaction
$tx = WalletTransaction::findById($txId);

if (!$tx) {
    flash('Transaction not found.', 'error');
    redirect('/admin/wallet/pending.php');
}

// Guard — only act on pending or contacted transactions
if (!in_array($tx['status'], ['pending', 'contacted'])) {
    flash('This transaction has already been processed.', 'error');
    redirect('/admin/wallet/pending.php');
}

$investorName = $tx['investor_first'] . ' ' . $tx['investor_last'];
$amount       = (float) $tx['amount'];
$now          = date('Y-m-d H:i:s');

try {
    db()->transaction(function() use (
        $tx, $txId, $action, $adminNote, $reference,
        $amount, $investorName, $now
    ) {
        // ── Update transaction status ─────────────────────
        WalletTransaction::updateStatus(
            $txId,
            $action,
            Auth::id(),
            $adminNote,
            $reference
        );

        // ── Balance adjustment on confirm ─────────────────
        if ($action === 'confirmed') {
            if ($tx['type'] === 'deposit') {
                // Credit investor wallet
                WalletBalance::credit($tx['user_id'], $amount);

            } elseif ($tx['type'] === 'withdrawal') {
                // Debit investor wallet
                $success = WalletBalance::debit($tx['user_id'], $amount);

                if (!$success) {
                    throw new \RuntimeException(
                        "Insufficient balance to process withdrawal of {$amount} for user {$tx['user_id']}"
                    );
                }
            }
        }

        // ── Notify investor ───────────────────────────────
        $notifData = match($action) {
            'contacted' => [
                'title'   => $tx['type'] === 'deposit'
                    ? 'Deposit Request — We\'ll Be In Touch'
                    : 'Withdrawal Request — We\'ll Be In Touch',
                'message' => 'Our team has reviewed your ' . $tx['type'] .
                             ' request for ' . formatMoney($amount) .
                             ' and will contact you shortly.',
            ],
            'confirmed' => [
                'title'   => $tx['type'] === 'deposit'
                    ? 'Deposit Confirmed ✓'
                    : 'Withdrawal Confirmed ✓',
                'message' => 'Your ' . $tx['type'] . ' of ' . formatMoney($amount) .
                             ' has been confirmed' .
                             ($tx['type'] === 'deposit'
                                 ? ' and added to your balance.'
                                 : ' and payment has been sent.'),
            ],
            'rejected'  => [
                'title'   => ucfirst($tx['type']) . ' Request Declined',
                'message' => 'Your ' . $tx['type'] . ' request for ' .
                             formatMoney($amount) . ' could not be processed.' .
                             ($adminNote ? ' Note: ' . $adminNote : ''),
            ],
        };

        db()->insert('notifications', [
            'user_id'      => $tx['user_id'],
            'type'         => 'transaction_' . $action,
            'title'        => $notifData['title'],
            'message'      => $notifData['message'],
            'related_type' => 'wallet_transaction',
            'related_id'   => $txId,
            'is_read'      => 0,
            'created_at'   => $now,
        ]);

        // ── Update message thread status ──────────────────
        if ($action === 'confirmed' || $action === 'rejected') {
            db()->query(
                "UPDATE message_threads
                 SET status = 'closed', updated_at = ?
                 WHERE context_type = 'transaction' AND context_id = ?",
                [$now, $txId]
            );
        }

        // ── Audit ─────────────────────────────────────────
        Auth::audit(Auth::id(), 'wallet_' . $action, 'wallet_transaction', $txId, [
            'type'   => $tx['type'],
            'amount' => $amount,
        ]);
    });

    // ── Send email notification ───────────────────────────
    if ($action === 'confirmed') {
        if ($tx['type'] === 'deposit') {
            Mailer::sendDepositConfirmed($tx['investor_email'], $investorName, $amount);
        } else {
            Mailer::sendWithdrawalUpdate($tx['investor_email'], $investorName, $amount, 'confirmed');
        }
    } elseif ($action === 'contacted') {
        Mailer::sendWithdrawalUpdate($tx['investor_email'], $investorName, $amount, 'contacted');
    }

    // ── Flash message ─────────────────────────────────────
    $label = match($action) {
        'contacted' => 'marked as contacted',
        'confirmed' => 'confirmed — balance updated',
        'rejected'  => 'rejected',
    };

    flash(
        ucfirst($tx['type']) . ' of ' . formatMoney($amount) .
        ' for ' . $investorName . ' has been ' . $label . '.',
        $action === 'rejected' ? 'warning' : 'success'
    );

} catch (\RuntimeException $e) {
    flash('Could not process: ' . $e->getMessage(), 'error');
} catch (\Throwable $e) {
    error_log('wallet-confirm error: ' . $e->getMessage());
    flash('Something went wrong. Please try again.', 'error');
}

redirect('/admin/wallet/pending.php');