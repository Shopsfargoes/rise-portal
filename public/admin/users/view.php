<?php
// ============================================================
// RISE CAPITAL GROUP — Admin: View Single Investor
// ============================================================
require_once __DIR__ . '/../../app/bootstrap.php';

use Rise\Core\Auth;

Auth::requireAdmin();

$id = (int) get('id');
if (!$id) redirect('/admin/users/index.php');

// ── Fetch investor ────────────────────────────────────────
$investor = db()->fetchOne(
    "SELECT u.id, u.uuid, u.email, u.status, u.role, u.last_login, u.created_at, u.invited_by,
            p.first_name, p.last_name, p.phone, p.avatar_path,
            p.address_line1, p.city, p.state, p.country,
            p.accredited, p.accredited_at, p.notes,
            COALESCE(wb.balance, 0) AS wallet_balance
     FROM users u
     LEFT JOIN user_profiles p ON p.user_id = u.id
     LEFT JOIN wallet_balances wb ON wb.user_id = u.id
     WHERE u.id = ? AND u.role = 'investor'
     LIMIT 1",
    [$id]
);

if (!$investor) redirect('/admin/users/index.php');

$activeTab = get('tab', 'overview');

// ── Tab data ──────────────────────────────────────────────

// Investments
$investments = db()->fetchAll(
    "SELECT i.*, p.title AS project_title, p.status AS project_status, p.location
     FROM investments i
     JOIN projects p ON p.id = i.project_id
     WHERE i.user_id = ?
     ORDER BY i.invested_at DESC",
    [$id]
);

$totalInvested = array_sum(array_column($investments, 'amount'));

// Transactions
$transactions = db()->fetchAll(
    "SELECT * FROM wallet_transactions
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 50",
    [$id]
);

// Distributions
$distributions = db()->fetchAll(
    "SELECT di.*, d.distribution_date, d.description AS dist_desc,
            p.title AS project_title
     FROM distribution_items di
     JOIN distributions d ON d.id = di.distribution_id
     JOIN projects p ON p.id = d.project_id
     WHERE di.user_id = ?
     ORDER BY d.distribution_date DESC",
    [$id]
);

$totalDistributions = array_sum(array_column($distributions, 'amount'));

// Documents accessible to this investor
$documents = db()->fetchAll(
    "SELECT d.*, dc.name AS category_name, p.title AS project_title
     FROM documents d
     LEFT JOIN document_categories dc ON dc.id = d.category_id
     LEFT JOIN projects p ON p.id = d.project_id
     WHERE d.visibility IN ('investor','admin')
     ORDER BY d.created_at DESC",
    []
);

$statusBadge = [
    'active'    => 'badge-green',
    'pending'   => 'badge-orange',
    'suspended' => 'badge-red',
];

$txStatusBadge = [
    'pending'   => 'badge-orange',
    'contacted' => 'badge-blue',
    'confirmed' => 'badge-green',
    'rejected'  => 'badge-red',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= e($investor['first_name'] . ' ' . $investor['last_name']) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
</head>
<body class="admin-layout">

<?php require_once BASE_PATH . '/views/partials/sidebar-admin.php'; ?>

<div class="main-content">
    <?php require_once BASE_PATH . '/views/partials/topbar.php'; ?>

    <div class="page-body">

        <!-- Page header -->
        <div class="page-header">
            <div style="display:flex; align-items:center; gap:16px;">
                <div class="avatar avatar-lg">
                    <?php if (!empty($investor['avatar_path'])): ?>
                        <img src="<?= e($investor['avatar_path']) ?>" alt=""/>
                    <?php else: ?>
                        <?= strtoupper(substr($investor['first_name'] ?? '?', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="<?= APP_URL ?>/admin/users/index.php" class="back-link">← Back to Investors</a>
                    <h1 class="page-title"><?= e($investor['first_name'] . ' ' . $investor['last_name']) ?></h1>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:4px;">
                        <span style="font-size:13px; color:var(--muted);"><?= e($investor['email']) ?></span>
                        <span class="badge <?= $statusBadge[$investor['status']] ?? 'badge-grey' ?>">
                            <?= ucfirst($investor['status']) ?>
                        </span>
                        <?php if ($investor['accredited']): ?>
                            <span class="badge badge-gold">✓ Accredited</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="flex gap-8">
                <a href="<?= APP_URL ?>/admin/users/edit.php?id=<?= $id ?>" class="btn-secondary">Edit Profile</a>
                <a href="<?= APP_URL ?>/admin/messages/thread.php?investor_id=<?= $id ?>" class="btn-primary">Message Investor</a>
            </div>
        </div>

        <!-- Stats row -->
        <div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:24px;">
            <div class="stat-card">
                <div class="stat-label">Wallet Balance</div>
                <div class="stat-value text-gold"><?= formatMoney((float)$investor['wallet_balance']) ?></div>
                <div class="stat-sub">Available funds</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Invested</div>
                <div class="stat-value"><?= formatCurrency($totalInvested) ?></div>
                <div class="stat-sub"><?= count($investments) ?> active investment<?= count($investments) !== 1 ? 's' : '' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Distributions</div>
                <div class="stat-value"><?= formatCurrency($totalDistributions) ?></div>
                <div class="stat-sub"><?= count($distributions) ?> payment<?= count($distributions) !== 1 ? 's' : '' ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Last Login</div>
                <div class="stat-value" style="font-size:18px;">
                    <?= $investor['last_login'] ? timeAgo($investor['last_login']) : 'Never' ?>
                </div>
                <div class="stat-sub">Joined <?= formatDate($investor['created_at']) ?></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-nav">
            <?php
            $tabs = [
                'overview'      => 'Overview',
                'investments'   => 'Investments (' . count($investments) . ')',
                'transactions'  => 'Transactions (' . count($transactions) . ')',
                'distributions' => 'Distributions (' . count($distributions) . ')',
                'documents'     => 'Documents (' . count($documents) . ')',
            ];
            foreach ($tabs as $key => $label):
            ?>
            <a href="?id=<?= $id ?>&tab=<?= $key ?>"
               class="tab-link <?= $activeTab === $key ? 'active' : '' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ── OVERVIEW TAB ── -->
        <?php if ($activeTab === 'overview'): ?>
        <div class="form-grid-2" style="gap:20px;">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Personal Details</div>
                    <a href="<?= APP_URL ?>/admin/users/edit.php?id=<?= $id ?>" class="btn-secondary btn-sm">Edit</a>
                </div>
                <table style="width:100%">
                    <tbody>
                        <tr><td style="color:var(--muted);font-size:12px;padding:8px 0;width:40%">Email</td><td><?= e($investor['email']) ?></td></tr>
                        <tr><td style="color:var(--muted);font-size:12px;padding:8px 0">Phone</td><td><?= e($investor['phone'] ?? '—') ?></td></tr>
                        <tr><td style="color:var(--muted);font-size:12px;padding:8px 0">Location</td><td><?= e(implode(', ', array_filter([$investor['city'], $investor['state'], $investor['country']])) ?: '—') ?></td></tr>
                        <tr><td style="color:var(--muted);font-size:12px;padding:8px 0">Accredited</td><td><?= $investor['accredited'] ? '<span class="badge badge-green">Verified ' . ($investor['accredited_at'] ? formatDate($investor['accredited_at']) : '') . '</span>' : '<span class="badge badge-grey">Not verified</span>' ?></td></tr>
                        <tr><td style="color:var(--muted);font-size:12px;padding:8px 0">Account Status</td><td><span class="badge <?= $statusBadge[$investor['status']] ?>"><?= ucfirst($investor['status']) ?></span></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Internal Notes</div>
                </div>
                <p style="font-size:13px; color:var(--muted); line-height:1.7;">
                    <?= $investor['notes'] ? nl2br(e($investor['notes'])) : '<em>No notes recorded.</em>' ?>
                </p>
                <div style="margin-top:16px;">
                    <a href="<?= APP_URL ?>/admin/users/edit.php?id=<?= $id ?>#notes" class="btn-secondary btn-sm">Edit Notes</a>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="card mt-16">
            <div class="card-header"><div class="card-title">Quick Actions</div></div>
            <div class="flex gap-8" style="flex-wrap:wrap;">
                <a href="<?= APP_URL ?>/admin/investments/create.php?user_id=<?= $id ?>" class="btn-secondary">+ Record Investment</a>
                <a href="<?= APP_URL ?>/admin/transactions/investor.php?user_id=<?= $id ?>" class="btn-secondary">View Full Transaction History</a>
                <a href="<?= APP_URL ?>/admin/messages/thread.php?investor_id=<?= $id ?>" class="btn-secondary">Open Message Thread</a>
                <a href="<?= APP_URL ?>/admin/documents/upload.php?user_id=<?= $id ?>" class="btn-secondary">Upload Document</a>
            </div>
        </div>

        <!-- ── INVESTMENTS TAB ── -->
        <?php elseif ($activeTab === 'investments'): ?>
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">Investments</div>
                    <div class="table-sub">Total invested: <?= formatCurrency($totalInvested) ?></div>
                </div>
                <a href="<?= APP_URL ?>/admin/investments/create.php?user_id=<?= $id ?>" class="btn-primary btn-sm">+ Record Investment</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Location</th>
                            <th>Project Status</th>
                            <th class="text-right">Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($investments)): ?>
                        <tr><td colspan="6" class="table-empty">No investments recorded yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($investments as $inv): ?>
                        <tr>
                            <td class="td-primary"><?= e($inv['project_title']) ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= e($inv['location'] ?? '—') ?></td>
                            <td><span class="badge badge-grey"><?= ucfirst(e($inv['project_status'])) ?></span></td>
                            <td class="text-right td-primary"><?= formatMoney((float)$inv['amount']) ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= formatDate($inv['invested_at']) ?></td>
                            <td><span class="badge <?= $inv['status'] === 'active' ? 'badge-green' : 'badge-grey' ?>"><?= ucfirst($inv['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── TRANSACTIONS TAB ── -->
        <?php elseif ($activeTab === 'transactions'): ?>
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Transaction History</div>
                <a href="<?= APP_URL ?>/admin/transactions/investor.php?user_id=<?= $id ?>" class="btn-secondary btn-sm">Full History →</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" class="table-empty">No transactions yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                        <tr>
                            <td>
                                <span class="badge <?= $tx['type'] === 'deposit' ? 'badge-green' : 'badge-orange' ?>">
                                    <?= $tx['type'] === 'deposit' ? '↓ Deposit' : '↑ Withdrawal' ?>
                                </span>
                            </td>
                            <td class="text-right td-primary"><?= formatMoney((float)$tx['amount']) ?></td>
                            <td><span class="badge <?= $txStatusBadge[$tx['status']] ?? 'badge-grey' ?>"><?= ucfirst($tx['status']) ?></span></td>
                            <td class="td-mono" style="font-size:11px;color:var(--muted);"><?= e($tx['reference'] ?? '—') ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= formatDateTime($tx['created_at']) ?></td>
                            <td>
                                <?php if (in_array($tx['status'], ['pending','contacted'])): ?>
                                <a href="<?= APP_URL ?>/admin/wallet/confirm.php?id=<?= $tx['id'] ?>" class="btn-secondary btn-sm">Action</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── DISTRIBUTIONS TAB ── -->
        <?php elseif ($activeTab === 'distributions'): ?>
        <div class="table-card">
            <div class="table-header">
                <div>
                    <div class="table-title">Distributions</div>
                    <div class="table-sub">Total received: <?= formatCurrency($totalDistributions) ?></div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($distributions)): ?>
                        <tr><td colspan="5" class="table-empty">No distributions yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($distributions as $d): ?>
                        <tr>
                            <td class="td-primary"><?= e($d['project_title']) ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= e($d['dist_desc'] ?? '—') ?></td>
                            <td class="text-right td-primary"><?= formatMoney((float)$d['amount']) ?></td>
                            <td><span class="badge <?= $d['status'] === 'paid' ? 'badge-green' : 'badge-orange' ?>"><?= ucfirst($d['status']) ?></span></td>
                            <td style="color:var(--muted);font-size:12px;"><?= formatDate($d['distribution_date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── DOCUMENTS TAB ── -->
        <?php elseif ($activeTab === 'documents'): ?>
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Accessible Documents</div>
                <a href="<?= APP_URL ?>/admin/documents/upload.php" class="btn-primary btn-sm">+ Upload Document</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Project</th>
                            <th>Visibility</th>
                            <th>Uploaded</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                        <tr><td colspan="6" class="table-empty">No documents uploaded yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td class="td-primary">📄 <?= e($doc['title']) ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= e($doc['category_name'] ?? '—') ?></td>
                            <td style="color:var(--muted);font-size:12px;"><?= e($doc['project_title'] ?? 'Company-wide') ?></td>
                            <td><span class="badge badge-grey"><?= ucfirst($doc['visibility']) ?></span></td>
                            <td style="color:var(--muted);font-size:12px;"><?= formatDate($doc['created_at']) ?></td>
                            <td>
                                <a href="<?= APP_URL ?>/download.php?uuid=<?= e($doc['uuid']) ?>"
                                   class="btn-secondary btn-sm" target="_blank">Download</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
