<?php
// ============================================================
// RISE CAPITAL GROUP — Investor: Market Updates
// ============================================================
require_once __DIR__ . '/../app/bootstrap.php';

use Rise\Core\Auth;
use Rise\Models\Post;

Auth::requireInvestor();

// Published market updates
$updates = Post::findAll(['type' => 'update', 'status' => 'published'], 5, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Market Updates — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css"/>
    <style>
        /* Chart containers */
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        .chart-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .chart-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chart-title { font-size: 14px; font-weight: 700; }
        .chart-sub   { font-size: 12px; color: var(--muted); }

        .chart-body { padding: 0; }

        /* Futures table */
        .futures-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 28px;
        }

        .futures-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        .futures-title { font-size: 15px; font-weight: 700; }
        .futures-sub   { font-size: 12px; color: var(--muted); margin-top: 3px; }

        .futures-group-label {
            padding: 8px 20px;
            background: var(--surface2);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        .futures-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr 1fr;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            transition: background .15s;
        }

        .futures-row:last-child { border-bottom: none; }
        .futures-row:hover { background: var(--surface2); }

        .futures-row .name {
            font-weight: 600;
            display: flex; align-items: center; gap: 8px;
        }

        .futures-row .value  { text-align: right; font-weight: 600; }
        .futures-row .change { text-align: right; }
        .futures-row .chg-pct { text-align: right; font-weight: 700; }
        .futures-row .other   { text-align: right; color: var(--muted); font-size: 12px; }

        .up   { color: var(--green); }
        .down { color: var(--red); }

        /* Update cards */
        .update-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-left: 3px solid var(--gold);
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 12px;
        }

        .update-title { font-size: 14px; font-weight: 700; margin-bottom: 6px; }
        .update-body  { font-size: 13px; color: var(--muted); line-height: 1.6; }
        .update-meta  { font-size: 11px; color: var(--muted2); margin-top: 8px; }

        @media (max-width: 700px) {
            .chart-grid   { grid-template-columns: 1fr; }
            .futures-row  { grid-template-columns: 2fr 1fr 1fr; }
            .futures-row .other { display: none; }
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
                <h1 class="page-title">Market Updates</h1>
                <p class="page-sub">Live energy futures and WTI crude oil charts</p>
            </div>
        </div>

        <!-- Charts -->
        <div class="chart-grid">

            <!-- WTI Crude chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">WTI Crude Oil (CL1!)</div>
                        <div class="chart-sub">Daily · USD per barrel</div>
                    </div>
                </div>
                <div class="chart-body">
                    <!-- TradingView Widget -->
                    <div class="tradingview-widget-container">
                        <div id="tv-chart-wti"></div>
                        <script type="text/javascript"
                                src="https://s3.tradingview.com/tv.js"></script>
                        <script type="text/javascript">
                        new TradingView.widget({
                            "width": "100%",
                            "height": 300,
                            "symbol": "NYMEX:CL1!",
                            "interval": "D",
                            "timezone": "America/Chicago",
                            "theme": "dark",
                            "style": "1",
                            "locale": "en",
                            "toolbar_bg": "#141414",
                            "enable_publishing": false,
                            "hide_top_toolbar": false,
                            "hide_legend": false,
                            "save_image": false,
                            "container_id": "tv-chart-wti",
                            "backgroundColor": "#141414",
                            "gridColor": "#2a2a2a"
                        });
                        </script>
                    </div>
                </div>
            </div>

            <!-- Natural Gas chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div>
                        <div class="chart-title">Natural Gas (NG1!)</div>
                        <div class="chart-sub">Daily · USD per MMBtu</div>
                    </div>
                </div>
                <div class="chart-body">
                    <div class="tradingview-widget-container">
                        <div id="tv-chart-ng"></div>
                        <script type="text/javascript">
                        new TradingView.widget({
                            "width": "100%",
                            "height": 300,
                            "symbol": "NYMEX:NG1!",
                            "interval": "D",
                            "timezone": "America/Chicago",
                            "theme": "dark",
                            "style": "1",
                            "locale": "en",
                            "toolbar_bg": "#141414",
                            "enable_publishing": false,
                            "hide_top_toolbar": false,
                            "save_image": false,
                            "container_id": "tv-chart-ng",
                            "backgroundColor": "#141414",
                            "gridColor": "#2a2a2a"
                        });
                        </script>
                    </div>
                </div>
            </div>

        </div>

        <!-- Energy Futures Table -->
        <div class="futures-section">
            <div class="futures-header">
                <div class="futures-title">Energy Futures</div>
                <div class="futures-sub">
                    Live crude, natural gas, and refined products — grouped quotes
                </div>
            </div>

            <!-- Column headers -->
            <div class="futures-row" style="background:var(--surface2);font-size:10px;
                 font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);
                 padding-top:8px;padding-bottom:8px;">
                <div>Name</div>
                <div style="text-align:right;">Value</div>
                <div style="text-align:right;">Change</div>
                <div style="text-align:right;">Chg%</div>
                <div style="text-align:right;">Open</div>
                <div style="text-align:right;">High</div>
                <div style="text-align:right;">Low</div>
            </div>

            <!-- CRUDE OIL -->
            <div class="futures-group-label">Crude Oil</div>

            <div class="futures-row">
                <div class="name">
                    <div style="width:8px;height:8px;border-radius:50%;background:#555;"></div>
                    WTI Crude
                </div>
                <div class="value" id="wti-value">—</div>
                <div class="change down" id="wti-change">—</div>
                <div class="chg-pct down" id="wti-pct">—</div>
                <div class="other" id="wti-open">—</div>
                <div class="other" id="wti-high">—</div>
                <div class="other" id="wti-low">—</div>
            </div>

            <div class="futures-row">
                <div class="name">
                    <div style="width:8px;height:8px;border-radius:50%;background:#555;"></div>
                    Brent Crude
                </div>
                <div class="value" id="brent-value">—</div>
                <div class="change down" id="brent-change">—</div>
                <div class="chg-pct down" id="brent-pct">—</div>
                <div class="other" id="brent-open">—</div>
                <div class="other" id="brent-high">—</div>
                <div class="other" id="brent-low">—</div>
            </div>

            <!-- NATURAL GAS -->
            <div class="futures-group-label">Natural Gas</div>

            <div class="futures-row">
                <div class="name">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--gold);"></div>
                    Natural Gas (UNG)
                </div>
                <div class="value" id="ng-value">—</div>
                <div class="change up" id="ng-change">—</div>
                <div class="chg-pct up" id="ng-pct">—</div>
                <div class="other" id="ng-open">—</div>
                <div class="other" id="ng-high">—</div>
                <div class="other" id="ng-low">—</div>
            </div>

            <!-- REFINED PRODUCTS -->
            <div class="futures-group-label">Refined Products</div>

            <div class="futures-row">
                <div class="name">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--gold);"></div>
                    Gasoline (UGA)
                </div>
                <div class="value" id="gas-value">—</div>
                <div class="change down" id="gas-change">—</div>
                <div class="chg-pct down" id="gas-pct">—</div>
                <div class="other" id="gas-open">—</div>
                <div class="other" id="gas-high">—</div>
                <div class="other" id="gas-low">—</div>
            </div>

            <div class="futures-row">
                <div class="name">
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--blue);"></div>
                    Refiners (CRAK)
                </div>
                <div class="value" id="crak-value">—</div>
                <div class="change down" id="crak-change">—</div>
                <div class="chg-pct down" id="crak-pct">—</div>
                <div class="other" id="crak-open">—</div>
                <div class="other" id="crak-high">—</div>
                <div class="other" id="crak-low">—</div>
            </div>

        </div>

        <!-- Market update posts -->
        <?php if (!empty($updates)): ?>
        <div style="margin-bottom:8px;">
            <h2 style="font-size:16px;font-weight:700;margin-bottom:16px;">Latest Updates</h2>
            <?php foreach ($updates as $update): ?>
            <div class="update-card">
                <div class="update-title"><?= e($update['title']) ?></div>
                <div class="update-body">
                    <?= truncate(strip_tags($update['body']), 200) ?>
                    <a href="<?= APP_URL ?>/investor/news.php?slug=<?= e($update['slug']) ?>"
                       style="color:var(--gold);font-weight:600;margin-left:6px;">
                        Read more →
                    </a>
                </div>
                <div class="update-meta">
                    <?= $update['published_at'] ? formatDateTime($update['published_at']) : '' ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
// Static data matching the screenshots — in production
// you would fetch real-time prices from a market data API
const quotes = {
    wti:   { value: 89.40, change: -4.16, pct: -4.45, open: 93.39, high: 93.69, low: 87.77 },
    brent: { value: 92.94, change: -3.35, pct: -3.48, open: 96.37, high: 96.45, low: 91.75 },
    ng:    { value: 11.18, change:  0.27, pct:  2.47, open: 11.09, high: 11.41, low: 11.09 },
    gas:   { value: 106.40,change: -2.01, pct: -1.85, open: 105.54,high: 106.91,low: 104.91 },
    crak:  { value: 47.50, change: -0.39, pct: -0.81, open: 47.39, high: 47.75, low: 47.03 },
};

function populateTable() {
    Object.entries(quotes).forEach(([key, q]) => {
        const isUp = q.change >= 0;
        const cls  = isUp ? 'up' : 'down';
        const sign = isUp ? '+' : '';

        const val  = document.getElementById(`${key}-value`);
        const chg  = document.getElementById(`${key}-change`);
        const pct  = document.getElementById(`${key}-pct`);
        const open = document.getElementById(`${key}-open`);
        const high = document.getElementById(`${key}-high`);
        const low  = document.getElementById(`${key}-low`);

        if (val)  { val.textContent  = q.value.toFixed(2); }
        if (chg)  { chg.textContent  = sign + q.change.toFixed(2); chg.className = `change ${cls}`; }
        if (pct)  { pct.textContent  = sign + q.pct.toFixed(2) + '%'; pct.className = `chg-pct ${cls}`; }
        if (open) open.textContent   = q.open.toFixed(2);
        if (high) high.textContent   = q.high.toFixed(2);
        if (low)  low.textContent    = q.low.toFixed(2);
    });
}

populateTable();
</script>

</body>
</html>