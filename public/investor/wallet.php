<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Wallet
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\WalletBalance;
use Rise\Models\WalletTransaction;

Auth::requireInvestor();

$userId  = Auth::id();
$balance = WalletBalance::get($userId);

// Recent transactions
$transactions = WalletTransaction::findAll(['user_id' => $userId], 10, 0);

// Any pending requests
$pendingDeposit = db()->fetchOne(
    "SELECT id FROM wallet_transactions
     WHERE user_id = ? AND type = 'deposit' AND status IN ('pending','contacted')
     LIMIT 1",
    [$userId]
);

$pendingWithdrawal = db()->fetchOne(
    "SELECT id FROM wallet_transactions
     WHERE user_id = ? AND type = 'withdrawal' AND status IN ('pending','contacted')
     LIMIT 1",
    [$userId]
);

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Wallet — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        /* Balance card */
        .balance-card {
            background: linear-gradient(135deg, #1a1200 0%, #0f0f0f 70%);
            border: 1px solid var(--gold-border);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 28px;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 240px; height: 240px;
            background: radial-gradient(circle, rgba(201,146,42,.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .balance-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 8px;
        }

        .balance-amount {
            font-size: 44px;
            font-weight: 900;
            color: var(--text);
            line-height: 1;
            margin-bottom: 8px;
        }

        .balance-sub {
            font-size: 12px;
            color: var(--muted);
        }

        /* Action cards */
        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .action-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
        }

        .action-card.deposit-card  { border-color: var(--green-border); }
        .action-card.withdraw-card { border-color: #3a2800; }

        .action-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-sub {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* Pending notice */
        .pending-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: var(--orange-bg);
            border: 1px solid #3a2800;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 13px;
            color: var(--orange);
            margin-bottom: 16px;
        }

        /* Amount input with currency prefix */
        .amount-wrap {
            position: relative;
        }

        .amount-prefix {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 700;
            color: var(--muted);
            font-size: 15px;
            pointer-events: none;
        }

        .amount-wrap input {
            padding-left: 28px;
            font-size: 20px;
            font-weight: 700;
        }

        /* Quick amount buttons */
        .quick-amounts {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .quick-btn {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            transition: all .15s;
        }

        .quick-btn:hover {
            border-color: var(--gold);
            color: var(--gold);
        }

        /* How it works */
        .how-it-works {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .how-step {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            color: var(--muted);
        }

        .how-num {
            width: 20px; height: 20px;
            border-radius: 50%;
            background: var(--surface2);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 700;
            flex-shrink: 0;
            color: var(--text);
        }

        @media (max-width: 640px) {
            .action-grid { grid-template-columns: 1fr; }
            .balance-amount { font-size: 32px; }
        }
    </style>
</head>
<body class="investor-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-investor.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <div class="page-header">
            <div>
                <h1 class="page-title">Wallet</h1>
                <p class="page-sub">Manage your deposits and withdrawals</p>
            </div>
            <a href="<?= APP_URL ?>/investor/transactions.php" class="btn-secondary">
                View All Transactions
            </a>
        </div>

        <?php foreach ($flash as $msg): ?>
        <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['message']) ?></div>
        <?php endforeach; ?>

        <!-- Balance card -->
        <div class="balance-card">
            <div class="balance-label">💳 Available Balance</div>
            <div class="balance-amount"><?= formatMoney($balance) ?></div>
            <div class="balance-sub">
                United States Dollar (USD)
                &nbsp;·&nbsp;
                Last updated <?= date('M j, Y') ?>
            </div>
        </div>

        <!-- Deposit + Withdrawal forms -->
        <div class="action-grid">

            <!-- DEPOSIT -->
            <div class="action-card deposit-card">
                <div class="action-title">
                    <span style="color:var(--green);">↓</span> Deposit Funds
                </div>
                <div class="action-sub">
                    Submit a deposit request. Our team will contact you
                    with wire transfer instructions within 1 business day.
                </div>

                <?php if ($pendingDeposit): ?>
                <div class="pending-notice">
                    <span>⏳</span>
                    <div>
                        You already have a pending deposit request.
                        <a href="<?= APP_URL ?>/investor/transactions.php"
                           style="color:var(--orange);font-weight:600;">View status →</a>
                    </div>
                </div>
                <?php else: ?>

                <form method="POST" action="<?= APP_URL ?>/app/Actions/investor/deposit-request.php">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label>Deposit Amount (USD) <span class="required">*</span></label>
                        <div class="amount-wrap">
                            <span class="amount-prefix">$</span>
                            <input type="number" name="amount" id="depositAmount"
                                   required min="1000" step="0.01"
                                   placeholder="0.00"/>
                        </div>
                        <div class="quick-amounts">
                            <?php foreach ([5000, 10000, 25000, 50000, 100000] as $amt): ?>
                            <button type="button" class="quick-btn"
                                    onclick="document.getElementById('depositAmount').value=<?= $amt ?>">
                                <?= formatCurrency($amt) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <span class="form-hint">Minimum deposit: $1,000</span>
                    </div>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label>Note (optional)</label>
                        <textarea name="note" rows="2"
                                  placeholder="Any details about this deposit..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="width:100%;">
                        Submit Deposit Request →
                    </button>
                </form>

                <div class="how-it-works">
                    <div style="font-size:11px;font-weight:700;color:var(--muted);
                                letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;">
                        How it works
                    </div>
                    <div class="how-step">
                        <div class="how-num">1</div>
                        <span>Submit your deposit amount below</span>
                    </div>
                    <div class="how-step">
                        <div class="how-num">2</div>
                        <span>Our team contacts you with wire transfer details</span>
                    </div>
                    <div class="how-step">
                        <div class="how-num">3</div>
                        <span>Send the funds and notify us via the message thread</span>
                    </div>
                    <div class="how-step">
                        <div class="how-num">4</div>
                        <span>Admin confirms receipt — balance updates instantly</span>
                    </div>
                </div>

                <?php endif; ?>
            </div>

            <!-- WITHDRAWAL -->
            <div class="action-card withdraw-card">
                <div class="action-title">
                    <span style="color:var(--orange);">↑</span> Withdraw Funds
                </div>
                <div class="action-sub">
                    Request a withdrawal from your available balance. Our team
                    will arrange payment within 3-5 business days.
                </div>

                <?php if ($pendingWithdrawal): ?>
                <div class="pending-notice">
                    <span>⏳</span>
                    <div>
                        You already have a pending withdrawal request.
                        <a href="<?= APP_URL ?>/investor/transactions.php"
                           style="color:var(--orange);font-weight:600;">View status →</a>
                    </div>
                </div>
                <?php elseif ($balance <= 0): ?>
                <div class="pending-notice" style="background:var(--red-bg);border-color:var(--red-border);color:#ff6b6b;">
                    <span>⚠</span>
                    <span>Your balance is $0.00. You need to deposit funds before withdrawing.</span>
                </div>
                <?php else: ?>

                <form method="POST" action="<?= APP_URL ?>/app/Actions/investor/withdrawal-request.php">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label>Withdrawal Amount (USD) <span class="required">*</span></label>
                        <div class="amount-wrap">
                            <span class="amount-prefix">$</span>
                            <input type="number" name="amount" id="withdrawAmount"
                                   required min="100" step="0.01"
                                   max="<?= $balance ?>"
                                   placeholder="0.00"
                                   oninput="checkWithdrawal(this.value)"/>
                        </div>
                        <div class="quick-amounts">
                            <button type="button" class="quick-btn"
                                    onclick="document.getElementById('withdrawAmount').value=<?= $balance ?>">
                                All (<?= formatMoney($balance) ?>)
                            </button>
                            <?php if ($balance >= 1000): ?>
                            <button type="button" class="quick-btn"
                                    onclick="document.getElementById('withdrawAmount').value=<?= round($balance * 0.5, 2) ?>">
                                50%
                            </button>
                            <?php endif; ?>
                        </div>
                        <span class="form-hint" id="withdrawHint">
                            Available: <?= formatMoney($balance) ?>
                        </span>
                    </div>

                    <div class="form-group" style="margin-bottom:16px;">
                        <label>Note (optional)</label>
                        <textarea name="note" rows="2"
                                  placeholder="Bank details or any other relevant notes..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary"
                            id="withdrawBtn"
                            style="width:100%;background:var(--surface2);
                                   border:1px solid var(--border);color:var(--text);">
                        Submit Withdrawal Request →
                    </button>
                </form>

                <div class="how-it-works">
                    <div style="font-size:11px;font-weight:700;color:var(--muted);
                                letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;">
                        How it works
                    </div>
                    <div class="how-step">
                        <div class="how-num">1</div>
                        <span>Submit your withdrawal amount</span>
                    </div>
                    <div class="how-step">
                        <div class="how-num">2</div>
                        <span>Our team reviews and contacts you to confirm payout details</span>
                    </div>
                    <div class="how-step">
                        <div class="how-num">3</div>
                        <span>Funds sent within 3–5 business days</span>
                    </div>
                    <div class="how-step">
                        <div class="how-num">4</div>
                        <span>Admin confirms — balance updates automatically</span>
                    </div>
                </div>

                <?php endif; ?>
            </div>

        </div>

        <!-- Recent transactions -->
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">Recent Transactions</div>
                    <div class="table-sub">Your last 10 deposits and withdrawals</div>
                </div>
                <a href="<?= APP_URL ?>/investor/transactions.php" class="btn-secondary btn-sm">
                    View All
                </a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Note</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="5" class="table-empty">
                                No transactions yet. Submit a deposit to get started.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <span class="badge <?= $tx['type'] === 'deposit' ? 'badge-green' : 'badge-orange' ?>">
                                    <?= $tx['type'] === 'deposit' ? '↓ Deposit' : '↑ Withdrawal' ?>
                                </span>
                            </td>
                            <td class="text-right" style="font-weight:700;
                                color:<?= $tx['type'] === 'deposit' ? 'var(--green)' : 'var(--orange)' ?>;">
                                <?= $tx['type'] === 'deposit' ? '+' : '–' ?><?= formatMoney((float)$tx['amount']) ?>
                            </td>
                            <td>
                                <span class="badge <?= WalletTransaction::statusBadge($tx['status']) ?>">
                                    <?= WalletTransaction::statusLabel($tx['status']) ?>
                                </span>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">
                                <?= $tx['note'] ? truncate(e($tx['note']), 50) : '—' ?>
                            </td>
                            <td style="color:var(--muted);font-size:12px;">
                                <?= formatDateTime($tx['created_at']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
const maxBalance = <?= $balance ?>;

function checkWithdrawal(val) {
    const amount  = parseFloat(val) || 0;
    const btn     = document.getElementById('withdrawBtn');
    const hint    = document.getElementById('withdrawHint');

    if (!btn) return;

    if (amount > maxBalance) {
        hint.textContent  = '⚠ Amount exceeds your available balance of $' + maxBalance.toLocaleString();
        hint.style.color  = 'var(--red)';
        btn.disabled      = true;
        btn.style.opacity = '.5';
    } else if (amount < 100) {
        hint.textContent  = 'Minimum withdrawal: $100';
        hint.style.color  = 'var(--muted)';
        btn.disabled      = true;
        btn.style.opacity = '.5';
    } else {
        hint.textContent  = 'Available: <?= formatMoney($balance) ?>';
        hint.style.color  = 'var(--muted)';
        btn.disabled      = false;
        btn.style.opacity = '1';
        btn.style.background = 'var(--gold)';
        btn.style.color      = '#000';
        btn.style.border     = 'none';
    }
}
</script>

</body>
</html>