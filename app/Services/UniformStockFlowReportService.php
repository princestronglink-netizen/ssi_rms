<?php

namespace App\Services;

class UniformStockFlowReportService
{
    private const COMPANY_NAME    = 'STRONGLINK SERVICES';
    private const COMPANY_TAGLINE = 'Manpower and Housekeeping Services Provider';
    private const COMPANY_DEPT    = 'HR DEPARTMENT';
    private const COMPANY_ADDRESS = 'RL Bldg., Francisco Village, Brgy. Pulong Sta. Cruz, Santa Rosa, Laguna 4026';
    private const COMPANY_PHONE   = 'Tel no.: (049) 539-3215';
    private const COMPANY_LOGO    = '/images/logo.png';

    // ─────────────────────────────────────────────────────────────────────────
    // Public entry point
    // ─────────────────────────────────────────────────────────────────────────

    public static function generate(array $payload): string
    {
        $from      = $payload['date_from']  ?? '';
        $to        = $payload['date_to']    ?? '';
        $sections  = $payload['sections']   ?? [];
        $metrics   = $payload['metrics']    ?? [];
        $genTime   = now()->timezone('Asia/Manila')->format('M d, Y h:i A');
        $fmtDate   = fn (string $d): string => date('d M Y', strtotime($d));
        $fromFmt   = $from ? $fmtDate($from) : '—';
        $toFmt     = $to   ? $fmtDate($to)   : '—';
        $title     = 'Uniform Stock Flow Report';

        $bodyHtml  = '';

        // ── Cover / summary KPIs ──────────────────────────────────────────────
        $bodyHtml .= self::renderKpis($metrics);

        // ── Requested sections ────────────────────────────────────────────────
        if (in_array('stock_summary', $sections) && !empty($payload['stock_summary'])) {
            $bodyHtml .= self::renderStockSummary($payload['stock_summary']);
        }

        if (in_array('issuances', $sections) && !empty($payload['issuance_by_item'])) {
            $bodyHtml .= self::renderIssuanceByItem($payload['issuance_by_item']);
        }

        if (in_array('issuance_by_site', $sections) && !empty($payload['issuance_by_site'])) {
            $bodyHtml .= self::renderIssuanceBySite($payload['issuance_by_site']);
        }

        if (in_array('issuance_by_person', $sections) && !empty($payload['issuance_by_person'])) {
            $bodyHtml .= self::renderIssuanceByPerson($payload['issuance_by_person']);
        }

        if (in_array('restocks', $sections) && !empty($payload['restocks'])) {
            $bodyHtml .= self::renderRestocks($payload['restocks']);
        }

        if (in_array('returns', $sections) && !empty($payload['returns'])) {
            $bodyHtml .= self::renderReturns($payload['returns']);
        }

        return self::wrapDocument($title, $fromFmt, $toFmt, $genTime, $bodyHtml);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Section renderers
    // ─────────────────────────────────────────────────────────────────────────

    private static function renderKpis(array $metrics): string
    {
        $fmtNum   = fn ($n): string => number_format((int) $n);
        $totalIn  = $fmtNum($metrics['total_in']      ?? 0);
        $totalOut = $fmtNum($metrics['total_out']     ?? 0);
        $net      = (int) ($metrics['net']            ?? 0);
        $netStr   = ($net >= 0 ? '+' : '') . $fmtNum($net);
        $netColor = $net >= 0 ? '#16a34a' : '#dc2626';
        $curr     = $fmtNum($metrics['current_stock'] ?? 0);
        $netLabel = $net >= 0 ? 'Surplus period' : 'Deficit period';

        return <<<HTML
        <div class="section">
            <div class="section-title">Summary Metrics</div>
            <div class="kpi-grid">
                <div class="kpi kpi-in">
                    <div class="kpi-label">STOCK IN</div>
                    <div class="kpi-value" style="color:#16a34a;">{$totalIn}</div>
                    <div class="kpi-sub">Restocks + Returns</div>
                </div>
                <div class="kpi kpi-out">
                    <div class="kpi-label">STOCK OUT</div>
                    <div class="kpi-value" style="color:#dc2626;">{$totalOut}</div>
                    <div class="kpi-sub">Total Issued</div>
                </div>
                <div class="kpi kpi-net">
                    <div class="kpi-label">NET MOVEMENT</div>
                    <div class="kpi-value" style="color:{$netColor};">{$netStr}</div>
                    <div class="kpi-sub">{$netLabel}</div>
                </div>
                <div class="kpi kpi-stock">
                    <div class="kpi-label">CURRENT STOCK</div>
                    <div class="kpi-value" style="color:#0369a1;">{$curr}</div>
                    <div class="kpi-sub">Units on hand</div>
                </div>
            </div>
        </div>
        HTML;
    }

    private static function renderStockSummary(array $data): string
    {
        $rows    = '';
        $grouped = collect($data)->groupBy('item_name');
        $alt     = false;

        foreach ($grouped as $itemName => $variants) {
            $first = true;
            foreach ($variants as $v) {
                $size  = e($v['size']          ?? '—');
                $qty   = number_format((int) ($v['quantity'] ?? 0));
                $cat   = e($v['category_name'] ?? '—');
                $bg    = $alt ? "background:#f8fbff;" : '';

                $rows .= "<tr style='{$bg}'>";
                if ($first) {
                    $span  = count($variants);
                    $rows .= "<td rowspan='{$span}' class='td-main'><strong>" . e($itemName) . "</strong><br><span class='meta'>{$cat}</span></td>";
                    $first = false;
                }
                $rows .= "<td class='td-center'>{$size}</td><td class='td-right'><strong>{$qty}</strong></td></tr>";
            }
            $alt = !$alt;
        }

        return <<<HTML
        <div class="section">
            <div class="section-title">Current Stock On Hand</div>
            <table>
                <thead><tr>
                    <th>Item</th>
                    <th class="th-center">Size</th>
                    <th class="th-right">Qty On Hand</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    private static function renderIssuanceByItem(array $data): string
    {
        $rows = '';
        $alt  = false;

        foreach ($data as $row) {
            $label    = e($row['label']    ?? '—');
            $category = e($row['category'] ?? '—');
            $total    = number_format((int) ($row['total'] ?? 0));
            $bg       = $alt ? "background:#f8fbff;" : '';

            $typeHtml = '';
            foreach ($row['issuance_types'] ?? [] as $typeName => $typeData) {
                $sub   = number_format((int) ($typeData['subtotal'] ?? 0));
                $tName = e(ucwords(str_replace('_', ' ', $typeName)));
                $sizes = '';
                foreach ($typeData['sizes'] ?? [] as $sz => $sqty) {
                    $sizes .= '<span class="chip">' . e($sz ?: '—') . ': ' . number_format((int)$sqty) . '</span>';
                }
                $typeHtml .= "<div class='type-row'><span class='pill'>{$tName}</span> <strong>{$sub} pcs</strong> {$sizes}</div>";
            }

            $rows .= "<tr style='{$bg}'>
                <td class='td-main'><strong>{$label}</strong><br><span class='meta'>{$category}</span></td>
                <td>{$typeHtml}</td>
                <td class='td-right'><strong>{$total}</strong></td>
            </tr>";
            $alt = !$alt;
        }

        return <<<HTML
        <div class="section">
            <div class="section-title">Issuances by Item</div>
            <table>
                <thead><tr>
                    <th>Item</th>
                    <th>Breakdown by Issuance Type</th>
                    <th class="th-right">Total Pcs</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    private static function renderIssuanceBySite(array $data): string
    {
        $rows = '';
        $alt  = false;

        foreach ($data as $row) {
            $label = e($row['label'] ?? 'Unknown');
            $total = number_format((int) ($row['total'] ?? 0));
            $bg    = $alt ? "background:#f8fbff;" : '';

            $typeHtml = '';
            foreach ($row['issuance_types'] ?? [] as $typeName => $typeData) {
                $sub   = number_format((int) ($typeData['subtotal'] ?? 0));
                $tName = e(ucwords(str_replace('_', ' ', $typeName)));
                $items = '';
                foreach ($typeData['items'] ?? [] as $iName => $iqty) {
                    $items .= '<span class="chip">' . e($iName) . ': ' . number_format((int)$iqty) . '</span>';
                }
                $typeHtml .= "<div class='type-row'><span class='pill'>{$tName}</span> <strong>{$sub} pcs</strong> {$items}</div>";
            }

            $rows .= "<tr style='{$bg}'>
                <td class='td-main'><strong>{$label}</strong></td>
                <td>{$typeHtml}</td>
                <td class='td-right'><strong>{$total}</strong></td>
            </tr>";
            $alt = !$alt;
        }

        return <<<HTML
        <div class="section">
            <div class="section-title">Issuances by Site</div>
            <table>
                <thead><tr>
                    <th>Site</th>
                    <th>Breakdown by Issuance Type</th>
                    <th class="th-right">Total Pcs</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    private static function renderIssuanceByPerson(array $data): string
    {
        $rows = '';
        $alt  = false;

        foreach ($data as $row) {
            $label = e($row['label'] ?? 'Unknown');
            $site  = e($row['site']  ?? '—');
            $total = number_format((int) ($row['total'] ?? 0));
            $bg    = $alt ? "background:#f8fbff;" : '';

            $typeHtml = '';
            foreach ($row['issuance_types'] ?? [] as $typeName => $typeData) {
                $sub   = number_format((int) ($typeData['subtotal'] ?? 0));
                $tName = e(ucwords(str_replace('_', ' ', $typeName)));
                $items = '';
                foreach ($typeData['items'] ?? [] as $iName => $iqty) {
                    $items .= '<span class="chip">' . e($iName) . ': ' . number_format((int)$iqty) . '</span>';
                }
                $typeHtml .= "<div class='type-row'><span class='pill'>{$tName}</span> <strong>{$sub} pcs</strong> {$items}</div>";
            }

            $rows .= "<tr style='{$bg}'>
                <td class='td-main'><strong>{$label}</strong><br><span class='meta'>{$site}</span></td>
                <td>{$typeHtml}</td>
                <td class='td-right'><strong>{$total}</strong></td>
            </tr>";
            $alt = !$alt;
        }

        return <<<HTML
        <div class="section">
            <div class="section-title">Issuances by Person</div>
            <table>
                <thead><tr>
                    <th>Person</th>
                    <th>Breakdown</th>
                    <th class="th-right">Total Pcs</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    private static function renderRestocks(array $data): string
    {
        $rows = '';
        $alt  = false;

        foreach ($data as $r) {
            $item = e($r['item_name']     ?? '—');
            $cat  = e($r['category_name'] ?? '—');
            $size = e($r['size']          ?? '—');
            $qty  = number_format((int) ($r['delivered_quantity'] ?? 0));
            $date = e($r['delivered_at']  ?? '—');
            $bg   = $alt ? "background:#f8fbff;" : '';

            $rows .= "<tr style='{$bg}'>
                <td class='td-main'><strong>{$item}</strong><br><span class='meta'>{$cat}</span></td>
                <td class='td-center'>{$size}</td>
                <td class='td-right'><strong>{$qty}</strong></td>
                <td class='td-center'>{$date}</td>
            </tr>";
            $alt = !$alt;
        }

        return <<<HTML
        <div class="section">
            <div class="section-title">Restocks</div>
            <table>
                <thead><tr>
                    <th>Item</th>
                    <th class="th-center">Size</th>
                    <th class="th-right">Qty Delivered</th>
                    <th class="th-center">Delivered At</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    private static function renderReturns(array $data): string
    {
        $rows = '';
        $alt  = false;

        foreach ($data as $r) {
            $item = e($r['item_name']     ?? '—');
            $cat  = e($r['category_name'] ?? '—');
            $size = e($r['size']          ?? '—');
            $qty  = number_format((int) ($r['returned_quantity'] ?? 0));
            $date = e($r['returned_at']   ?? '—');
            $bg   = $alt ? "background:#f8fbff;" : '';

            $rows .= "<tr style='{$bg}'>
                <td class='td-main'><strong>{$item}</strong><br><span class='meta'>{$cat}</span></td>
                <td class='td-center'>{$size}</td>
                <td class='td-right'><strong>{$qty}</strong></td>
                <td class='td-center'>{$date}</td>
            </tr>";
            $alt = !$alt;
        }

        return <<<HTML
        <div class="section">
            <div class="section-title">Returns (Added Back to Stock)</div>
            <table>
                <thead><tr>
                    <th>Item</th>
                    <th class="th-center">Size</th>
                    <th class="th-right">Qty Returned</th>
                    <th class="th-center">Returned At</th>
                </tr></thead>
                <tbody>{$rows}</tbody>
            </table>
        </div>
        HTML;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTML wrapper — same toolbar pattern as UniformTransmittalService
    // ─────────────────────────────────────────────────────────────────────────

    private static function wrapDocument(
        string $title,
        string $fromFmt,
        string $toFmt,
        string $genTime,
        string $bodyHtml
    ): string {
        $safeTitle = e($title);
        $cn        = e(self::COMPANY_NAME);
        $tagline   = e(self::COMPANY_TAGLINE);
        $dept      = e(self::COMPANY_DEPT);
        $addr      = e(self::COMPANY_ADDRESS);
        $phone     = e(self::COMPANY_PHONE);
        $logo      = e(self::COMPANY_LOGO);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$safeTitle} — {$fromFmt} to {$toFmt}</title>
<style>
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #d1d9e6; color: #000; }

/* ── Toolbar (same as transmittal) ── */
.toolbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 9999;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #1e3a5f;
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 28px;
    box-shadow: 0 2px 16px rgba(0,0,0,.4);
}
.tbar-l  { display: flex; align-items: center; gap: 16px; }
.tbar-title { font-size: 14px; font-weight: 800; color: #fff; letter-spacing: .02em; }
.tbar-sub   { font-size: 10px; color: #94a3b8; margin-top: 1px; }
.tbar-badge { background: #2563eb; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 14px; border-radius: 999px; }
.tbar-r { display: flex; gap: 8px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; border-radius: 7px; font-size: 12px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-blue  { background: #2563eb; color: #fff; }
.btn-ghost { background: rgba(255,255,255,.1); color: #e2e8f0; border: 1px solid rgba(255,255,255,.2); }

/* ── Page wrapper ── */
.pages { padding: 80px 32px 48px; display: flex; flex-direction: column; align-items: center; gap: 28px; }
.a4 {
    width: 210mm;
    background: #fff;
    box-shadow: 0 8px 32px rgba(0,0,0,.22);
    border-radius: 2px;
    overflow: hidden;
}

/* ── Report page ── */
.report-page { padding: 8mm 10mm 10mm; }

/* ── Company header (same layout as transmittal) ── */
.co-header {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 0; padding: 2mm 0 2mm;
    border-bottom: 2.5px solid #1e3a5f;
    text-align: center;
}
.co-logo-wrap {
    width: 220px; height: 90px;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.co-logo-img {
    width: 100%; height: 100%; object-fit: contain; display: block;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.co-tagline { font-size: 8.5pt; color: #475569; letter-spacing: .01em; margin-top: -8px; line-height: 1; }

/* ── Dept banner ── */
.dept-row {
    width: 100%; border-collapse: collapse;
    border: 1.5px solid #000; border-top: none;
    margin-bottom: 0;
}
.dept-row td {
    padding: 2.5mm 3mm; border: 1px solid #000;
    vertical-align: middle;
    background: #1a237e; color: #fff;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.dept-name { font-size: 13pt; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; text-align: center; }
.report-label { font-size: 10pt; font-weight: 700; color: #93c5fd; letter-spacing: .04em; text-align: center; padding: 2.5mm 6mm; }

/* ── Date range meta ── */
.date-meta {
    width: 100%; border-collapse: collapse;
    border: 1px solid #ccc; border-top: none;
    margin-bottom: 6mm;
}
.date-meta td { padding: 1.8mm 3mm; font-size: 10pt; border-right: 1px solid #ccc; vertical-align: middle; }
.date-meta td:last-child { border-right: none; }
.date-meta .dk { font-weight: 700; width: 22mm; text-align: right; color: #333; border-right: 1px solid #ccc; }
.date-meta .dv { font-weight: 700; font-size: 10.5pt; }

/* ── KPI grid ── */
.kpi-grid { display: flex; gap: 10px; margin-bottom: 6mm; flex-wrap: wrap; }
.kpi {
    flex: 1; min-width: 40mm; padding: 3mm 4mm;
    border-radius: 4px; border-left: 4px solid transparent;
}
.kpi-in    { background: #f0fdf4; border-color: #16a34a; }
.kpi-out   { background: #fef2f2; border-color: #dc2626; }
.kpi-net   { background: #f5f3ff; border-color: #7c3aed; }
.kpi-stock { background: #f0f9ff; border-color: #0369a1; }
.kpi-label { font-size: 7pt; text-transform: uppercase; letter-spacing: .08em; color: #64748b; font-weight: 700; }
.kpi-value { font-size: 18pt; font-weight: 700; margin: 2px 0 1px; line-height: 1.1; }
.kpi-sub   { font-size: 7.5pt; color: #94a3b8; }

/* ── Sections ── */
.section { margin-bottom: 8mm; }
.section-title {
    font-size: 11pt; font-weight: 700; color: #1a237e;
    padding: 2mm 3mm; margin-bottom: 0;
    background: #e8edf7; border-left: 4px solid #1a237e;
    text-transform: uppercase; letter-spacing: .04em;
}

/* ── Tables ── */
table { width: 100%; border-collapse: collapse; font-size: 9pt; }
thead tr { background: #1a237e; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
thead th {
    padding: 2mm 3mm; text-align: left;
    color: #fff; font-size: 8pt; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em;
    border: 1px solid #1a237e;
}
.th-center { text-align: center; }
.th-right  { text-align: right; }
tbody td { padding: 2mm 3mm; border: 1px solid #e2eaf5; vertical-align: top; }
.td-main   { min-width: 40mm; }
.td-center { text-align: center; }
.td-right  { text-align: right; }
.meta { color: #7f96b6; font-size: 8pt; }

/* ── Type breakdown ── */
.type-row  { margin-bottom: 3px; font-size: 8.5pt; }
.pill {
    display: inline-block; padding: 1px 6px; border-radius: 4px;
    background: #e0eaff; color: #1a237e;
    font-size: 7.5pt; font-weight: 700; text-transform: uppercase;
    letter-spacing: .04em; margin-right: 4px;
}
.chip {
    display: inline-block; background: #f1f5f9; color: #475569;
    padding: 1px 5px; border-radius: 3px; font-size: 7.5pt;
    margin: 2px 2px 0 0;
}

/* ── Addr footer ── */
.addr-footer {
    text-align: center; font-size: 7.5pt; color: #555;
    padding-top: 3mm; border-top: 1px solid #ccc;
    margin-top: 8mm; line-height: 1.6;
}

/* ── Print ── */
@media print {
    @page { size: A4 portrait; margin: 0; }
    html, body { width: 210mm; background: #fff !important; }
    .toolbar { display: none !important; }
    .pages { padding: 0; gap: 0; background: #fff; }
    .a4 { width: 210mm; box-shadow: none; border-radius: 0; }
    .kpi-grid, .kpi, thead tr, .pill, .chip {
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
}
</style>
</head>
<body>

<div class="toolbar">
    <div class="tbar-l">
        <div>
            <div class="tbar-title">📊 {$safeTitle}</div>
            <div class="tbar-sub">Generated {$genTime}</div>
        </div>
        <div class="tbar-badge">{$fromFmt} &nbsp;→&nbsp; {$toFmt}</div>
    </div>
    <div class="tbar-r">
        <button class="btn btn-blue" onclick="window.print()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print
        </button>
        <button class="btn btn-ghost" onclick="window.close()">✕ Close</button>
    </div>
</div>

<div class="pages">
<div class="a4">
<div class="report-page">

    <div class="co-header">
        <div class="co-logo-wrap">
            <img src="{$logo}" alt="{$cn}" class="co-logo-img">
        </div>
        <div class="co-tagline">{$tagline}</div>
    </div>

    <table class="dept-row">
        <tr>
            <td class="dept-name">{$dept}</td>
            <td class="report-label">Stock Flow Report</td>
        </tr>
    </table>

    <table class="date-meta">
        <tr>
            <td class="dk">Period:</td>
            <td class="dv">{$fromFmt} &nbsp;→&nbsp; {$toFmt}</td>
            <td class="dk">Generated:</td>
            <td class="dv">{$genTime}</td>
        </tr>
    </table>

    {$bodyHtml}

    <div class="addr-footer">
        {$addr}<br>{$phone}
    </div>

</div>
</div>
</div>

</body>
</html>
HTML;
    }
}