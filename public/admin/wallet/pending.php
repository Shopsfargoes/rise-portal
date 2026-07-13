<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: Pending Wallet Requests
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\WalletTransaction;

Auth::requireAdmin();

$pending = WalletTransaction::getPending();
$flash   = getFlash();

// Split into deposits and withdrawals
$deposits     = array_filter($pending, fn($t) => $t['type'] === 'deposit');
$withdrawals  = array_filter($pending, fn($t) => $t['type'] === 'withdrawal');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pending Requests — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        .request-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 14px;
            transition: border-color .2s;
        }

        .request-card.deposit-card    { border-left: 3px solid var(--green); }
        .request-card.withdrawal-card { border-left: 3px solid var(--orange); }
        .request-card.contacted-card  { border-left: 3px solid var(--blue); }

        .request-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .request-investor {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .request-amount {
            font-size: 24px;
            font-weight: 900;
        }

        .request-amount.deposit    { color: var(--green); }
        .request-amount.withdrawal { color: var(--orange); }

        .request-meta {
            font-size: 12px;
            color: var(--muted);
            margin-top: 3px;
        }

        .request-note {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
            color: var(--text2);
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .request-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            margin-top: 28px;
        }

        .section-header h2 {
            font-size: 16px;
            font-weight: 700;
        }

        .section-header:first-child { margin-top: 0; }

        .empty-section {
            background: var(--surface);
            border: 1px dashed var(--border);
            border-radius: 10px;
            padding: 28px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        /* Balance pill on card */
        .balance-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 99px;
            padding: 3px 10px;
            font-size: 11px;
            color: var(--muted);
        }
    </style>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Pending Requests</h1>
                <p class="page-sub">
                    <?= count($pending) ?> request<?= count($pending) !== 1 ? 's' : '' ?> awaiting action
                </p>
            </div>
            <a href="<?= APP_URL ?>/admin/wallet/index.php" class="btn-secondary">
                All Transactions →
            </a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- DEPOSITS -->
        <div class="section-header">
            <h2>↓ Deposit Requests</h2>
            <?php if (!empty($deposits)): ?>
            <span class="badge badge-green"><?= count($deposits) ?> pending</span>
            <?php endif; ?>
        </div>

        <?php if (empty($deposits)): ?>
        <div class="empty-section">No pending deposit requests.</div>
        <?php else: ?>
        <?php foreach ($deposits as $tx): ?>
        <div class="request-card <?= $tx['status'] === 'contacted' ? 'contacted-card' : 'deposit-card' ?>">
            <div class="request-header">
                <div class="request-investor">
                    <div class="avatar avatar-sm">
                        <?php if (!empty($tx['avatar_path'])): ?>
                        <img src="<?= e($tx['avatar_path']) ?>" alt=""/>
                        <?php else: ?>
                        <?= strtoupper(substr($tx['investor_first'] ?? '?', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:14px;">
                            <?= e($tx['investor_first'] . ' ' . $tx['investor_last']) ?>
                        </div>
                        <div class="request-meta">
                            <?= e($tx['investor_email']) ?>
                            · Balance:
                            <strong style="color:var(--text);">
                                <?= formatMoney((float)$tx['investor_balance']) ?>
                            </strong>
                        </div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="request-amount deposit">
                        +<?= formatMoney((float)$tx['amount']) ?>
                    </div>
                    <div class="request-meta">
                        <?= formatDateTime($tx['created_at']) ?>
                        · <span class="badge <?= WalletTransaction::statusBadge($tx['status']) ?>">
                            <?= WalletTransaction::statusLabel($tx['status']) ?>
                          </span>
                    </div>
                </div>
            </div>

            <?php if ($tx['note']): ?>
            <div class="request-note">
                💬 Investor note: <?= nl2br(e($tx['note'])) ?>
            </div>
            <?php endif; ?>

            <div class="request-actions">
                <?php if ($tx['status'] === 'pending'): ?>
                <form method="POST" action="<?= APP_URL ?>/admin/wallet/confirm.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"/>
                    <input type="hidden" name="action" value="contacted"/>
                    <button type="submit" class="btn-secondary">
                        ✉ Mark as Contacted
                    </button>
                </form>
                <?php endif; ?>

                <form method="POST" action="<?= APP_URL ?>/admin/wallet/confirm.php"
                      onsubmit="return confirmAction('confirm deposit of <?= formatMoney((float)$tx['amount']) ?>')">
                    <?= csrfField() ?>
                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"/>
                    <input type="hidden" name="action" value="confirmed"/>
                    <input type="hidden" name="reference" id="ref_<?= $tx['id'] ?>" value=""/>
                    <button type="submit" class="btn-primary">
                        ✓ Confirm Deposit Received
                    </button>
                </form>

                <form method="POST" action="<?= APP_URL ?>/admin/wallet/confirm.php"
                      onsubmit="return confirm('Reject this deposit request?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"/>
                    <input type="hidden" name="action" value="rejected"/>
                    <button type="submit" class="btn-danger">Reject</button>
                </form>

                <a href="<?= APP_URL ?>/admin/users/view.php?id=<?= $tx['user_id'] ?>"
                   class="btn-secondary">View Investor</a>

                <a href="<?= APP_URL ?>/admin/messages/thread.php?investor_id=<?= $tx['user_id'] ?>"
                   class="btn-secondary">Open Message Thread</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- WITHDRAWALS -->
        <div class="section-header">
            <h2>↑ Withdrawal Requests</h2>
            <?php if (!empty($withdrawals)): ?>
            <span class="badge badge-orange"><?= count($withdrawals) ?> pending</span>
            <?php endif; ?>
        </div>

        <?php if (empty($withdrawals)): ?>
        <div class="empty-section">No pending withdrawal requests.</div>
        <?php else: ?>
        <?php foreach ($withdrawals as $tx): ?>
        <div class="request-card <?= $tx['status'] === 'contacted' ? 'contacted-card' : 'withdrawal-card' ?>">
            <div class="request-header">
                <div class="request-investor">
                    <div class="avatar avatar-sm">
                        <?php if (!empty($tx['avatar_path'])): ?>
                        <img src="<?= e($tx['avatar_path']) ?>" alt=""/>
                        <?php else: ?>
                        <?= strtoupper(substr($tx['investor_first'] ?? '?', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:14px;">
                            <?= e($tx['investor_first'] . ' ' . $tx['investor_last']) ?>
                        </div>
                        <div class="request-meta">
                            <?= e($tx['investor_email']) ?>
                            · Balance:
                            <strong style="color:<?= (float)$tx['investor_balance'] >= (float)$tx['amount'] ? 'var(--green)' : 'var(--red)' ?>;">
                                <?= formatMoney((float)$tx['investor_balance']) ?>
                            </strong>
                        </div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div class="request-amount withdrawal">
                        –<?= formatMoney((float)$tx['amount']) ?>
                    </div>
                    <div class="request-meta">
                        <?= formatDateTime($tx['created_at']) ?>
                        · <span class="badge <?= WalletTransaction::statusBadge($tx['status']) ?>">
                            <?= WalletTransaction::statusLabel($tx['status']) ?>
                          </span>
                    </div>
                </div>
            </div>

            <?php if ($tx['note']): ?>
            <div class="request-note">
                💬 Investor note: <?= nl2br(e($tx['note'])) ?>
            </div>
            <?php endif; ?>

            <!-- Warn if insufficient balance -->
            <?php if ((float)$tx['investor_balance'] < (float)$tx['amount']): ?>
            <div class="alert alert-error" style="margin-bottom:14px;">
                ⚠ Investor balance (<?= formatMoney((float)$tx['investor_balance']) ?>)
                is less than withdrawal amount. Review before confirming.
            </div>
            <?php endif; ?>

            <div class="request-actions">
                <?php if ($tx['status'] === 'pending'): ?>
                <form method="POST" action="<?= APP_URL ?>/admin/wallet/confirm.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"/>
                    <input type="hidden" name="action" value="contacted"/>
                    <button type="submit" class="btn-secondary">✉ Mark as Contacted</button>
                </form>
                <?php endif; ?>

                <form method="POST" action="<?= APP_URL ?>/admin/wallet/confirm.php"
                      onsubmit="return confirmAction('confirm payout of <?= formatMoney((float)$tx['amount']) ?>')">
                    <?= csrfField() ?>
                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"/>
                    <input type="hidden" name="action" value="confirmed"/>
                    <button type="submit" class="btn-primary">✓ Confirm Payout Sent</button>
                </form>

                <form method="POST" action="<?= APP_URL ?>/admin/wallet/confirm.php"
                      onsubmit="return confirm('Reject this withdrawal request?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"/>
                    <input type="hidden" name="action" value="rejected"/>
                    <button type="submit" class="btn-danger">Reject</button>
                </form>

                <a href="<?= APP_URL ?>/admin/messages/thread.php?investor_id=<?= $tx['user_id'] ?>"
                   class="btn-secondary">Open Message Thread</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<script>
function confirmAction(msg) {
    return confirm('Are you sure you want to ' + msg + '?\n\nThis will update the investor\'s balance.');
}
</script>

</body>
</html>