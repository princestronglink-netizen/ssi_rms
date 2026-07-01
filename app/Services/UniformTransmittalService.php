<?php

namespace App\Services;

use App\Models\UniformIssuances;

class UniformTransmittalService
{
    private const COMPANY_NAME    = 'STRONGLINK SERVICES';
    private const COMPANY_TAGLINE = 'Manpower and Housekeeping Services Provider';
    private const COMPANY_DEPT    = 'HR DEPARTMENT';
    private const COMPANY_ADDRESS = 'RL Bldg., Francisco Village, Brgy. Pulong Sta. Cruz, Santa Rosa, Laguna 4026';
    private const COMPANY_PHONE   = 'Tel no.: (049) 539-3215';
    private const COMPANY_LOGO    = '/images/logo.png';

    /**
     * Generate transmittal from a saved Transmittal DB record.
     * Uses items_summary stored at the time of transmittal creation.
     */
    public static function generateFromTransmittal(
        \App\Models\UniformIssuances $issuance,
        \App\Models\Transmittals $transmittal
    ): string {
        $issuance->loadMissing('site', 'uniformIssuanceType');

        $rows = [];
        foreach ($transmittal->items_summary as $row) {
            $rows[] = [
                'employee'  => $row['employee']  ?? '—',
                'item_name' => $row['item_name'] ?? '—',
                'size'      => $row['size']      ?? '',
                'qty'       => (int) ($row['qty'] ?? 0),
            ];
        }

        $rows = self::backfillEmployees($issuance, $rows);

        $data = [
            'transmittal_number' => $transmittal->transmittal_number,
            'transmitted_by'     => $transmittal->transmitted_by,
            'transmitted_to'     => $transmittal->transmitted_to,
            'issuance_type'      => $issuance->uniformIssuanceType?->uniform_issuance_type_name ?? '—',
            'purpose'            => $transmittal->purpose ?? '',
            'instructions'       => $transmittal->instructions ?? '',
            'date'               => \Carbon\Carbon::parse($transmittal->transmitted_at)->timezone('Asia/Manila')->format('F d, Y'),
            'rows'               => self::groupRowsByEmployee($rows),
        ];

        $siteName = $issuance->site?->site_name ?? '';
        return self::wrapDocument([$data], "Transmittal {$transmittal->transmittal_number} — {$siteName}");
    }

    /**
     * Generate transmittal from a single issuance.
     * Uses current released_quantity from DB (complete state).
     */
    public static function generateFromIssuance(
        UniformIssuances $issuance,
        string $transmittedTo = '—',
        string $transmittedBy = '—',
        string $purpose       = '',
        string $instructions  = ''
    ): string {
        $issuance->loadMissing(
            'site',
            'uniformIssuanceType',
            'uniformIssuanceRecipient.position',
            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant'
        );

        $data = self::buildDataFromIssuance(
            $issuance,
            $transmittedTo,
            $transmittedBy,
            $purpose,
            $instructions
        );

        $siteName = $issuance->site?->site_name ?? '';
        $title    = "Transmittal — {$siteName}";

        return self::wrapDocument([$data], $title);
    }

    /**
     * Generate transmittal from a saved Transmittal DB record.
     * Uses items_summary stored at the time of saving.
     */
    public static function generateFromSaved(
        UniformIssuances $issuance,
        \App\Models\Transmittals $transmittal
    ): string {
        $issuance->loadMissing('site', 'uniformIssuanceType');

        $rows = [];
        foreach ($transmittal->items_summary as $row) {
            $rows[] = [
                'employee'  => $row['employee']  ?? '—',
                'item_name' => $row['item_name'] ?? '—',
                'size'      => $row['size']      ?? '',
                'qty'       => (int) ($row['qty'] ?? 0),
            ];
        }

        $rows = self::backfillEmployees($issuance, $rows);

        $data = [
            'transmittal_number' => $transmittal->transmittal_number,
            'transmitted_by'     => $transmittal->transmitted_by,
            'transmitted_to'     => $transmittal->transmitted_to,
            'issuance_type'      => $issuance->uniformIssuanceType?->uniform_issuance_type_name ?? '—',
            'purpose'            => $transmittal->purpose ?? '',
            'instructions'       => $transmittal->instructions ?? '',
            'date'               => \Carbon\Carbon::parse($transmittal->transmitted_at)
                                        ->timezone('Asia/Manila')
                                        ->format('F d, Y'),
            'rows'               => self::groupRowsByEmployee($rows),
        ];

        $siteName = $issuance->site?->site_name ?? '';
        $title    = "Transmittal #{$transmittal->transmittal_number} — {$siteName}";

        return self::wrapDocument([$data], $title);
    }

    /**
     * Generate transmittal from a specific batch log (issued/partial/item_released).
     * Uses only what was released in that specific log entry.
     */
    public static function generateFromLog(
        UniformIssuances $issuance,
        \App\Models\UniformIssuanceLog $log,
        string $transmittedTo = '—',
        string $transmittedBy = '—',
        string $purpose       = '',
        string $instructions  = ''
    ): string {
        $issuance->loadMissing(
            'site',
            'uniformIssuanceType',
            'uniformIssuanceRecipient.position'
        );

        $noteData = json_decode($log->note ?? '[]', true);
        if (!is_array($noteData)) $noteData = [];

        $rows    = [];
        $logDate = \Carbon\Carbon::parse($log->created_at)->timezone('Asia/Manila')->format('F d, Y');

        // item_changed log — parse _to field
        if ($log->action === 'item_changed') {
            foreach ($noteData as $row) {
                $employeeName = trim($row['label'] ?? 'Unknown');
                $toLabel      = $row['_to'] ?? null;
                $newQty       = (int) ($row['released'] ?? 0);

                if ($toLabel && preg_match('/^(.+?)\s*\(([^)]+)\)\s*×\s*(\d+)$/', $toLabel, $m)) {
                    $rows[] = [
                        'employee'  => $employeeName,
                        'item_name' => trim($m[1]),
                        'size'      => trim($m[2]),
                        'qty'       => (int) $m[3],
                    ];
                } elseif ($toLabel) {
                    $rows[] = [
                        'employee'  => $employeeName,
                        'item_name' => $toLabel,
                        'size'      => '',
                        'qty'       => $newQty,
                    ];
                }
            }
        } else {
            // issued / partial / item_released — parse "Item Name (Size) — Employee" label
            foreach ($noteData as $row) {
                $label    = $row['label'] ?? '—';
                $released = (int) ($row['released'] ?? 0);
                if ($released <= 0) continue;

                $parts        = explode(' — ', $label, 2);
                $itemPart     = trim($parts[0] ?? $label);
                $employeeName = trim($parts[1] ?? 'Unknown');

                if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $itemPart, $m)) {
                    $itemName = trim($m[1]);
                    $size     = trim($m[2]);
                } else {
                    $itemName = $itemPart;
                    $size     = '';
                }

                $rows[] = [
                    'employee'  => $employeeName,
                    'item_name' => $itemName,
                    'size'      => $size,
                    'qty'       => $released,
                ];
            }
        }

        $data = [
            'transmitted_to' => $transmittedTo,
            'transmitted_by' => $transmittedBy,
            'issuance_type'  => $issuance->uniformIssuanceType?->uniform_issuance_type_name ?? '—',
            'purpose'        => $purpose,
            'instructions'   => $instructions,
            'date'           => $logDate,
            'rows'           => self::groupRowsByEmployee($rows),
        ];

        $siteName = $issuance->site?->site_name ?? '';
        $title    = "Transmittal — {$siteName}";

        return self::wrapDocument([$data], $title);
    }

    private static function buildDataFromIssuance(
        UniformIssuances $issuance,
        string $transmittedTo,
        string $transmittedBy,
        string $purpose,
        string $instructions
    ): array {
        $rows = [];

        foreach ($issuance->uniformIssuanceRecipient as $recipient) {
            foreach ($recipient->uniformIssuanceItem as $item) {
                $qty = (int) ($item->released_quantity ?: $item->quantity);
                if ($qty <= 0) continue;

                $rows[] = [
                    'employee'  => $recipient->employee_name ?? '—',
                    'item_name' => $item->uniformItem?->uniform_item_name ?? "Item #{$item->uniform_item_id}",
                    'size'      => $item->uniformItemVariant?->uniform_item_size ?? '',
                    'qty'       => $qty,
                ];
            }
        }

        $dateSource = $issuance->issued_at ?? $issuance->partial_at ?? now();

        return [
            'transmitted_to' => $transmittedTo,
            'transmitted_by' => $transmittedBy,
            'issuance_type'  => $issuance->uniformIssuanceType?->uniform_issuance_type_name ?? '—',
            'purpose'        => $purpose,
            'instructions'   => $instructions,
            'date'           => \Carbon\Carbon::parse($dateSource)->timezone('Asia/Manila')->format('F d, Y'),
            'rows'           => self::groupRowsByEmployee($rows),
        ];
    }

    /**
     * Backfill missing employee names in saved items_summary rows by matching
     * item_name + size against the issuance's live recipient data.
     * Needed because some saved transmittals don't store 'employee' in items_summary.
     *
     * NOTE: This is a fallback. The permanent fix is to make sure whatever
     * code SAVES the Transmittals record (items_summary column) always
     * includes 'employee' => $recipient->employee_name for each row.
     */
    private static function backfillEmployees(UniformIssuances $issuance, array $rows): array
    {
        $issuance->loadMissing(
            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant'
        );

        // Build a queue of employee names per "item_name||size" key
        $queue = [];
        foreach ($issuance->uniformIssuanceRecipient as $recipient) {
            foreach ($recipient->uniformIssuanceItem as $item) {
                $itemName = trim($item->uniformItem?->uniform_item_name ?? '');
                $size     = trim($item->uniformItemVariant?->uniform_item_size ?? '');
                $key      = $itemName . '||' . $size;
                $queue[$key][] = $recipient->employee_name ?? 'Unknown';
            }
        }

        foreach ($rows as &$row) {
            $hasEmployee = !empty($row['employee']) && $row['employee'] !== '—';
            if ($hasEmployee) continue;

            $key = trim($row['item_name'] ?? '') . '||' . trim($row['size'] ?? '');

            if (!empty($queue[$key])) {
                $row['employee'] = array_shift($queue[$key]);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Group rows by employee. Each employee becomes ONE row; their items
     * (name + size + qty) are merged together for display in a single cell.
     */
    private static function groupRowsByEmployee(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $employee = trim($row['employee'] ?? '') ?: 'Unknown';

            if (!isset($groups[$employee])) {
                $groups[$employee] = [];
            }

            $key = trim($row['item_name'] ?? '') . '||' . trim($row['size'] ?? '');

            if (!isset($groups[$employee][$key])) {
                $groups[$employee][$key] = [
                    'item_name' => $row['item_name'] ?? '—',
                    'size'      => $row['size'] ?? '',
                    'qty'       => 0,
                ];
            }

            $groups[$employee][$key]['qty'] += (int) ($row['qty'] ?? 0);
        }

        $result = [];
        foreach ($groups as $employee => $items) {
            $result[] = [
                'employee' => $employee,
                'items'    => array_values($items),
            ];
        }

        return $result;
    }

    private static function renderPage(array $d): string
    {
        $txnNo        = e($d['transmittal_number'] ?? '—');
        $txnBy        = e($d['transmitted_by']);
        $txnTo        = e($d['transmitted_to']);
        $date         = e($d['date']);
        $issuanceType = e($d['issuance_type'] ?? '—');
        $purpose      = e($d['purpose'] ?? '');
        $instructions = e($d['instructions'] ?? '');
        $rows         = $d['rows'] ?? [];

        $cn      = e(self::COMPANY_NAME);
        $tagline = e(self::COMPANY_TAGLINE);
        $dept    = e(self::COMPANY_DEPT);
        $addr    = e(self::COMPANY_ADDRESS);
        $phone   = e(self::COMPANY_PHONE);
        $logo    = e(self::COMPANY_LOGO);

        $itemRows = '';
        $MIN_ROWS = 12;
        $no       = 0;

        foreach ($rows as $group) {
            $no++;
            $employeeName = e($group['employee']);

            $parts = [];
            foreach ($group['items'] as $item) {
                $itemName = e($item['item_name']);
                $size     = !empty($item['size']) ? ' (' . e($item['size']) . ')' : '';
                $qty      = (int) $item['qty'];
                $unit     = $qty === 1 ? 'pc' : 'pcs';
                $parts[]  = "{$itemName}{$size} - {$qty}{$unit}";
            }
            $desc = implode('; ', $parts);

            $itemRows .= "
                <tr class='data-row'>
                    <td class='c-no'>{$no}</td>
                    <td class='c-recipient'>{$employeeName}</td>
                    <td class='c-desc'>{$desc}</td>
                </tr>";
        }

        for ($f = count($rows); $f < $MIN_ROWS; $f++) {
            $itemRows .= "
                <tr class='data-row filler'>
                    <td class='c-no'>&nbsp;</td>
                    <td class='c-recipient'>&nbsp;</td>
                    <td class='c-desc'>&nbsp;</td>
                </tr>";
        }

        return <<<HTML
<div class="page">

    <div class="co-header">
        <div class="co-logo-wrap">
            <!-- <img src="{$logo}" alt="{$cn}" class="co-logo-img"> -->
             <h3 style="white-space:nowrap;">{$cn}</h3>
        </div>
        <div class="co-tagline">{$tagline}</div>
    </div>

    <table class="dept-row">
        <tr>
            <td class="dept-name">{$dept}</td>
            <td class="no-label">No.</td>
            <td class="no-value">{$txnNo}</td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-key">TO:</td>
            <td class="meta-val meta-val-to">{$txnTo}</td>
        </tr>
        <tr>
            <td class="meta-key">FROM:</td>
            <td class="meta-val">HR {$txnBy}</td>
        </tr>
        <tr>
            <td class="meta-key">DATE:</td>
            <td class="meta-val">{$date}</td>
        </tr>
        <tr class="meta-spacer">
            <td colspan="2" style="border:none !important;background:#fff;padding:2mm 0;">&nbsp;</td>
        </tr>
        <tr>
            <td colspan="2" class="meta-issuance-type">{$issuanceType}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr class="col-header">
                <th class="c-no">NO.</th>
                <th class="c-recipient">RECIPIENT</th>
                <th class="c-desc">ITEM DESCRIPTION</th>
            </tr>
        </thead>
        <tbody>
            {$itemRows}
        </tbody>
    </table>

    <table class="bottom-table">
        <tr>
            <td class="b-label">Purpose :</td>
            <td class="b-value b-bold">{$purpose}</td>
        </tr>
        <tr>
            <td class="b-label">Instructions : <span class="b-hint">(please specify)</span></td>
            <td class="b-value b-bold">{$instructions}</td>
        </tr>
        <tr>
            <td class="b-label">Received By :</td>
            <td class="b-value b-sig">
                <div class="sig-space"></div>
                <div class="sig-line-wrap">
                    <div class="sig-line"></div>
                    <div class="sig-line-label">Signature over printed name</div>
                </div>
            </td>
        </tr>
        <tr>
            <td class="b-label">Date :</td>
            <td class="b-value" style="text-align:center;vertical-align:middle;padding:3mm 3mm;">
                <div class="date-line"></div>
            </td>
        </tr>
    </table>

    <div class="addr-footer">
        {$addr}<br>{$phone}
    </div>

</div>
HTML;
    }

    public static function wrapDocument(array $pages, string $title): string
    {
        $safeTitle  = e($title);
        $genTime    = now()->timezone('Asia/Manila')->format('M d, Y h:i A');
        $totalPages = count($pages);

        $pagesHtml = '';
        foreach ($pages as $idx => $data) {
            $pb        = ($idx < $totalPages - 1) ? 'page-break-after:always;' : '';
            $pagesHtml .= "<div class='a4' style='{$pb}'>" . self::renderPage($data) . "</div>";
        }

        $totalItems = array_sum(array_map(
            fn ($d) => array_sum(array_map(fn ($g) => count($g['items']), $d['rows'])),
            $pages
        ));

        $grandTotal = array_sum(array_map(
            fn ($d) => array_sum(array_map(
                fn ($g) => array_sum(array_column($g['items'], 'qty')),
                $d['rows']
            )),
            $pages
        ));
        $pageBadge = $totalPages > 1 ? " &nbsp;·&nbsp; {$totalPages} pages" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$safeTitle}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,sans-serif;background:#d1d9e6;color:#000;}

.toolbar{
    position:fixed;top:0;left:0;right:0;z-index:9999;
    font-family:'Segoe UI',Arial,sans-serif;
    background:#1e3a5f;
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 28px;box-shadow:0 2px 16px rgba(0,0,0,.4);
}
.tbar-l{display:flex;align-items:center;gap:16px;}
.tbar-title{font-size:14px;font-weight:800;color:#fff;letter-spacing:.02em;}
.tbar-sub{font-size:10px;color:#94a3b8;margin-top:1px;}
.tbar-badge{background:#2563eb;color:#fff;font-size:11px;font-weight:700;padding:3px 14px;border-radius:999px;}
.tbar-r{display:flex;gap:8px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 20px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;border:none;text-decoration:none;transition:opacity .15s;}
.btn:hover{opacity:.85;}
.btn-blue{background:#2563eb;color:#fff;}
.btn-ghost{background:rgba(255,255,255,.1);color:#e2e8f0;border:1px solid rgba(255,255,255,.2);}

.pages{padding:72px 32px 48px;display:flex;flex-direction:column;align-items:center;gap:28px;}
.a4{
    width:210mm;height:297mm;
    background:#fff;box-shadow:0 8px 32px rgba(0,0,0,.22);
    border-radius:2px;overflow:hidden;display:flex;flex-direction:column;
}

.page{
    width:100%;height:100%;display:flex;flex-direction:column;
    padding:6mm 8mm 5mm;
    border:1.5px dashed #b0bec5;box-sizing:border-box;
}

.co-header{
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:0;padding:2mm 0 2mm;border-bottom:2.5px solid #1e3a5f;
    flex-shrink:0;margin-bottom:0;text-align:center;
}
.co-logo-wrap{
    width:220px;height:90px;flex-shrink:0;overflow:hidden;
    display:flex;align-items:center;justify-content:center;
}
.co-logo-img{
    width:100%;height:100%;object-fit:contain;display:block;
    -webkit-print-color-adjust:exact;print-color-adjust:exact;
}
.co-tagline{font-size:8.5pt;color:#475569;text-align:center;letter-spacing:.01em;margin-top:-8px;line-height:1;}

.dept-row{width:100%;border-collapse:collapse;border:1.5px solid #000;border-top:none;flex-shrink:0;}
.dept-row td{padding:2.5mm 3mm;border:1px solid #000;vertical-align:middle;background:#1a237e;color:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.dept-name{font-size:14pt;font-weight:700;text-transform:uppercase;letter-spacing:.06em;text-align:center;color:#fff;}
.no-label{font-size:9.5pt;font-weight:700;width:18mm;text-align:center;border-left:2px solid rgba(255,255,255,.3);white-space:nowrap;color:#fff;}
.no-value{font-size:12pt;font-weight:900;color:#93c5fd;width:30mm;text-align:center;white-space:nowrap;}

.meta-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid #ccc;border-top:none;flex-shrink:0;}
.meta-table td{padding:1.8mm 3mm;border-bottom:1px solid #ccc;border-right:1px solid #ccc;font-size:10pt;vertical-align:middle;}
.meta-table td:first-child{border-left:none;}
.meta-table tr:last-child td{border-bottom:none;}
.meta-key{font-weight:700;width:18mm;text-align:right;white-space:nowrap;border-right:1px solid #ccc;color:#333;}
.meta-val{text-align:center;}
.meta-val-to{font-weight:700;font-size:11pt;}
.meta-spacer td{padding:2mm 0;border:none !important;background:#fff;}
.meta-table tr.meta-spacer td{border:none !important;}
.meta-issuance-type{text-align:center;font-size:11pt;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:2.5mm 3mm;border-top:1px solid #ccc;border-right:none;color:#1a237e;}

.items-table{width:100%;border-collapse:collapse;border:1.5px solid #000;border-top:none;flex:1;table-layout:fixed;}
.col-header th{padding:2mm 2mm;font-size:8pt;font-weight:700;text-transform:uppercase;text-align:center;background:#1a237e;color:#fff;border:1px solid #000;letter-spacing:.04em;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.data-row td{border-bottom:1px solid #e0e0e0;border-right:1px solid #ccc;vertical-align:middle;height:9mm;}
.data-row td:last-child{border-right:none;}
.c-no{width:14mm;text-align:center;font-size:9.5pt;border-right:1px solid #000 !important;}
.c-recipient{width:38mm;text-align:left;font-size:9.5pt;font-weight:700;padding:1.5mm 3mm;border-right:1px solid #000 !important;color:#1a237e;text-transform:uppercase;}
.c-desc{text-align:left;font-size:9.5pt;padding:1.5mm 3mm;line-height:1.5;}

.bottom-table{width:100%;border-collapse:collapse;border:1.5px solid #000;border-top:1.5px solid #000;flex-shrink:0;}
.bottom-table td{border:1px solid #ccc;vertical-align:middle;padding:1.8mm 3mm;font-size:9.5pt;}
.b-label{width:42mm;font-weight:700;font-size:9pt;border-right:1.5px solid #000;white-space:nowrap;}
.b-hint{font-weight:400;font-style:italic;font-size:8pt;}
.b-value{text-align:center;}
.b-bold{font-weight:700;text-transform:uppercase;letter-spacing:.04em;font-size:9.5pt;}
.b-sig{padding:0 3mm 2mm;}
.sig-space{height:10mm;}
.sig-line-wrap{display:flex;flex-direction:column;align-items:center;width:65%;margin:0 auto;}
.sig-line{width:100%;border-bottom:1.5px solid #000;}
.sig-line-label{font-size:7.5pt;color:#555;margin-top:1mm;text-align:center;}
.date-line{width:55%;margin:2mm auto;border-bottom:1.5px solid #000;height:8mm;}

.addr-footer{flex-shrink:0;text-align:center;font-size:7.5pt;color:#555;padding-top:2mm;border-top:1px solid #ccc;margin-top:auto;line-height:1.6;}

@media print{
    @page{size:A4 portrait;margin:0;}
    html,body{width:210mm;background:#fff !important;}
    .toolbar{display:none !important;}
    .pages{padding:0;gap:0;background:#fff;}
    .a4{width:210mm;height:297mm;box-shadow:none;border-radius:0;}
    .page{border-color:#b0bec5;}
    .co-logo-img{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    .col-header th,.dept-row,.meta-table,.items-table,.bottom-table{-webkit-print-color-adjust:exact;print-color-adjust:exact;}
}
</style>
</head>
<body>

<div class="toolbar">
    <div class="tbar-l">
        <div>
            <div class="tbar-title">📮 {$safeTitle}</div>
            <div class="tbar-sub">Generated {$genTime}</div>
        </div>
        <div class="tbar-badge">{$totalItems} line(s) &nbsp;·&nbsp; {$grandTotal} total pcs{$pageBadge}</div>
    </div>
    <div class="tbar-r">
        <button class="btn btn-blue" onclick="window.print()">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="6 9 6 2 18 2 18 9"/>
                <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print ({$totalPages} page(s))
        </button>
        <button class="btn btn-ghost" onclick="window.close()">✕ Close</button>
    </div>
</div>

<div class="pages">
    {$pagesHtml}
</div>

</body>
</html>
HTML;
    }
}