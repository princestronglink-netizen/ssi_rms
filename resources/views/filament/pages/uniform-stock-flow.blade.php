<x-filament-panels::page>

<style>
/* ================================================================
   STOCK FLOW DASHBOARD — scoped styles
================================================================ */

.sf-wrap { display:flex; flex-direction:column; gap:20px; }

.sf-panel {
    background: var(--cl-base, #fff);
    border: 1px solid var(--cl-border, rgba(22,109,245,0.12));
    border-radius: 16px;
    box-shadow: var(--cl-shadow-sm, 0 6px 18px rgba(22,109,245,0.08));
}

.sf-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 0;
    flex-wrap: wrap;
    gap: 10px;
}

.sf-header-title { font-size:13px; font-weight:700; color:var(--cl-text,#0e1b34); letter-spacing:-0.01em; }
.sf-header-sub   { font-size:11px; color:var(--cl-text-3,#7f96b6); margin-top:2px; }

.sf-filters {
    display: grid;
    grid-template-columns: repeat(5,1fr) auto;
    gap: 12px;
    padding: 14px 20px 18px;
    align-items: end;
}
@media(max-width:1100px){ .sf-filters{ grid-template-columns:repeat(3,1fr); } .sf-reset-col{ grid-column:1/-1; } }
@media(max-width:640px) { .sf-filters{ grid-template-columns:1fr 1fr; } }

.sf-field { display:flex; flex-direction:column; gap:5px; }

.sf-label {
    font-size: 10px; font-weight:700; text-transform:uppercase;
    letter-spacing:0.08em; color:var(--cl-text-3,#7f96b6);
}

.sf-select, .sf-date {
    width:100%; padding:8px 28px 8px 10px; font-size:12.5px;
    font-family:inherit; font-weight:500; border-radius:10px;
    border:1px solid var(--cl-border,rgba(22,109,245,0.12));
    background:var(--cl-tinted,#f8fbff); color:var(--cl-text,#0e1b34);
    transition:border-color .18s, box-shadow .18s;
    appearance:none; -webkit-appearance:none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%237f96b6' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 10px center;
}
.sf-date { background-image:none; padding-right:10px; }
.sf-select:focus, .sf-date:focus {
    outline:none; border-color:rgba(22,109,245,0.5);
    box-shadow:0 0 0 3px rgba(22,109,245,0.12);
}
.dark .sf-select, .dark .sf-date {
    background-color:var(--cl-sunken,#0d1220);
    color:var(--cl-text,#e8edf7);
    border-color:var(--cl-border,rgba(22,109,245,0.15));
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%234d6080' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
}

.sf-reset-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 14px; font-size:12px; font-weight:600;
    border-radius:10px; border:1px solid var(--cl-border-strong,rgba(22,109,245,0.25));
    background:transparent; color:rgb(22,109,245); cursor:pointer;
    transition:background .18s; white-space:nowrap;
}
.sf-reset-btn:hover { background:rgba(22,109,245,0.08); }

.sf-print-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 14px; font-size:12px; font-weight:600;
    border-radius:10px; border:none;
    background:rgb(22,109,245); color:#fff; cursor:pointer;
    transition:background .18s, transform .15s; white-space:nowrap;
    box-shadow:0 2px 8px rgba(22,109,245,0.3);
}
.sf-print-btn:hover { background:rgb(15,90,210); transform:translateY(-1px); }
.sf-print-btn:active { transform:translateY(0); }

.sf-kpi-grid {
    display:grid; grid-template-columns:repeat(4,1fr); gap:14px;
}
@media(max-width:900px){ .sf-kpi-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:480px){ .sf-kpi-grid{ grid-template-columns:1fr; } }

.sf-kpi {
    padding:18px 20px; border-radius:14px;
    background:var(--cl-base,#fff);
    border:1px solid var(--cl-border,rgba(22,109,245,0.12));
    box-shadow:var(--cl-shadow-sm,0 6px 18px rgba(22,109,245,0.08));
    position:relative; overflow:hidden;
    transition:transform .22s ease, box-shadow .22s ease;
}
.sf-kpi:hover { transform:translateY(-3px); box-shadow:var(--cl-shadow-md,0 12px 30px rgba(22,109,245,0.12)); }
.sf-kpi::before {
    content:''; position:absolute; top:0; left:0; right:0;
    height:3px; border-radius:14px 14px 0 0;
}
.sf-kpi--in::before    { background:linear-gradient(90deg,#16a34a,#4ade80); }
.sf-kpi--out::before   { background:linear-gradient(90deg,#dc2626,#f87171); }
.sf-kpi--net::before   { background:linear-gradient(90deg,#7c3aed,#a78bfa); }
.sf-kpi--stock::before { background:linear-gradient(90deg,#0369a1,#38bdf8); }

.sf-kpi-icon {
    width:32px; height:32px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    margin-bottom:10px; font-size:16px;
}
.sf-kpi--in    .sf-kpi-icon { background:rgba(22,163,74,0.1);  color:#16a34a; }
.sf-kpi--out   .sf-kpi-icon { background:rgba(220,38,38,0.1);  color:#dc2626; }
.sf-kpi--net   .sf-kpi-icon { background:rgba(124,58,237,0.1); color:#7c3aed; }
.sf-kpi--stock .sf-kpi-icon { background:rgba(3,105,161,0.1);  color:#0369a1; }

.sf-kpi-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--cl-text-3,#7f96b6); }
.sf-kpi-value { font-size:28px; font-weight:700; font-family:'JetBrains Mono',monospace; margin-top:4px; line-height:1.1; }
.sf-kpi--in    .sf-kpi-value { color:#16a34a; }
.sf-kpi--out   .sf-kpi-value { color:#dc2626; }
.sf-kpi--net   .sf-kpi-value { color:#7c3aed; }
.sf-kpi--stock .sf-kpi-value { color:#0369a1; }

.sf-chart-body { padding:14px 20px 20px; height:310px; }

.sf-tabs {
    display:flex; gap:0;
    border-bottom:1px solid var(--cl-border,rgba(22,109,245,0.12));
    padding:0 20px; margin-top:4px;
}
.sf-tab {
    padding:10px 16px; font-size:12px; font-weight:600;
    color:var(--cl-text-3,#7f96b6); border:none;
    border-bottom:2px solid transparent; background:transparent;
    cursor:pointer; margin-bottom:-1px;
    transition:color .18s, border-color .18s;
    display:flex; align-items:center; gap:6px;
}
.sf-tab:hover { color:rgb(22,109,245); }
.sf-tab--active { color:rgb(22,109,245); border-bottom-color:rgb(22,109,245); }

.sf-summary-body { padding: 16px 20px 20px; }

.sf-table { width:100%; border-collapse:collapse; }
.sf-table thead th {
    font-size:10px; font-weight:700; text-transform:uppercase;
    letter-spacing:0.08em; color:var(--cl-text-3,#7f96b6);
    padding:7px 8px; text-align:left;
    background:var(--cl-tinted,#f8fbff);
    border-bottom:1px solid var(--cl-border,rgba(22,109,245,0.12));
}
.dark .sf-table thead th { background:var(--cl-sunken,#0d1220); }
.sf-table thead th:last-child { text-align:right; }
.sf-table tbody tr { transition:background .13s; }
.sf-table tbody tr:hover { background:rgba(22,109,245,0.04); }
.sf-table tbody td {
    padding:9px 8px;
    border-bottom:1px solid var(--cl-border,rgba(22,109,245,0.08));
    font-size:12.5px; color:var(--cl-text,#0e1b34);
}
.sf-table tbody td:last-child {
    text-align:right; font-family:'JetBrains Mono',monospace;
    font-size:12px; font-weight:600;
}
.sf-td-meta { font-size:11px; color:var(--cl-text-3,#7f96b6); }
.sf-td-rank {
    display:inline-flex; align-items:center; justify-content:center;
    width:20px; height:20px; border-radius:6px;
    background:rgba(22,109,245,0.08); color:rgb(22,109,245);
    font-size:10px; font-weight:700; font-family:'JetBrains Mono',monospace;
    margin-right:6px; flex-shrink:0;
}
.sf-bar-wrap {
    width:80px; height:4px; background:rgba(22,109,245,0.08);
    border-radius:99px; overflow:hidden; display:inline-block;
    vertical-align:middle; margin-left:6px;
}
.sf-bar {
    height:100%; border-radius:99px;
    background:linear-gradient(90deg,rgb(22,109,245),rgba(22,109,245,0.4));
    transition:width .5s ease;
}
.sf-badge {
    display:inline-flex; align-items:center;
    padding:2px 8px; border-radius:99px;
    font-size:10px; font-weight:700; font-family:'JetBrains Mono',monospace;
}
.sf-badge--blue   { background:rgba(22,109,245,0.1);  color:rgb(22,109,245); }
.sf-badge--green  { background:rgba(22,163,74,0.1);   color:#16a34a; }
.sf-badge--orange { background:rgba(234,88,12,0.1);   color:#ea580c; }
.sf-badge--purple { background:rgba(124,58,237,0.1);  color:#7c3aed; }
.sf-badge--gray   { background:rgba(100,116,139,0.1); color:#64748b; }

/* Issuance type pill colours */
.sf-type-pill {
    display:inline-flex; align-items:center; gap:3px;
    padding:2px 7px; border-radius:99px;
    font-size:10px; font-weight:700; font-family:'JetBrains Mono',monospace;
    white-space:nowrap;
}
.sf-type-pill--new         { background:rgba(22,163,74,0.1);   color:#16a34a; }
.sf-type-pill--replacement { background:rgba(234,88,12,0.1);   color:#ea580c; }
.sf-type-pill--additional  { background:rgba(22,109,245,0.1);  color:rgb(22,109,245); }
.sf-type-pill--returning   { background:rgba(124,58,237,0.1);  color:#7c3aed; }
.sf-type-pill--default     { background:rgba(100,116,139,0.1); color:#64748b; }

.sf-types-cell { display:flex; flex-direction:column; gap:3px; }

/* Nested type → sizes breakdown */
.sf-type-block { margin-bottom:8px; }
.sf-type-block:last-child { margin-bottom:0; }
.sf-type-block-header {
    display:flex; align-items:center; gap:6px; margin-bottom:4px;
}
.sf-type-sizes {
    padding-left:10px;
    border-left:2px solid var(--cl-border,rgba(22,109,245,0.12));
    display:flex; flex-direction:column; gap:2px; margin-top:2px;
}
.sf-type-size-row {
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; font-size:11px; color:var(--cl-text-3,#7f96b6);
}
.sf-type-size-qty {
    font-family:'JetBrains Mono',monospace; font-weight:600;
    font-size:11px; color:var(--cl-text,#0e1b34);
}

.sf-empty { text-align:center; color:var(--cl-text-3,#7f96b6); font-size:12px; padding:28px 0; }

/* ── PRINT STYLES ────────────────────────────────────────────── */
@media print {
    body * { visibility: hidden !important; }
    #sf-print-area, #sf-print-area * { visibility: visible !important; }
    #sf-print-area {
        position: fixed !important;
        inset: 0 !important;
        padding: 20px !important;
        background: #fff !important;
        color: #0e1b34 !important;
        font-family: 'JetBrains Mono', monospace, sans-serif !important;
        font-size: 11px !important;
    }
    .sf-print-header { margin-bottom: 18px; border-bottom: 2px solid #166df5; padding-bottom: 12px; }
    .sf-print-header h1 { font-size: 18px; font-weight: 700; color: #166df5; margin: 0 0 4px; }
    .sf-print-header p  { font-size: 11px; color: #7f96b6; margin: 0; }
    .sf-print-kpis { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 18px; }
    .sf-print-kpi { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
    .sf-print-kpi-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #7f96b6; }
    .sf-print-kpi-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
    .sf-print-kpi--in    .sf-print-kpi-value { color: #16a34a; }
    .sf-print-kpi--out   .sf-print-kpi-value { color: #dc2626; }
    .sf-print-kpi--net   .sf-print-kpi-value { color: #7c3aed; }
    .sf-print-kpi--stock .sf-print-kpi-value { color: #0369a1; }
    .sf-print-chart-wrap { margin-bottom: 18px; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
    .sf-print-chart-wrap h2 { font-size: 12px; font-weight: 700; color: #0e1b34; margin: 0 0 10px; }
    .sf-print-chart-img { width: 100%; max-height: 200px; object-fit: contain; }
    .sf-print-table-wrap h2 { font-size: 12px; font-weight: 700; color: #0e1b34; margin: 0 0 8px; }
    .sf-print-table { width: 100%; border-collapse: collapse; font-size: 10px; }
    .sf-print-table th {
        font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
        color: #7f96b6; padding: 6px 8px; text-align: left;
        background: #f8fbff; border-bottom: 1px solid #e2e8f0;
    }
    .sf-print-table td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; color: #0e1b34; }
    .sf-print-table td:last-child { text-align: right; font-weight: 700; }
    .sf-print-badge {
        display: inline-block; padding: 1px 6px; border-radius: 99px;
        font-size: 9px; font-weight: 700; background: rgba(22,109,245,0.1); color: #166df5;
    }
    .sf-print-type-pill {
        display: inline-block; padding: 1px 5px; border-radius: 99px;
        font-size: 8px; font-weight: 700; margin: 1px 2px 1px 0;
    }
    .sf-print-type-pill--new         { background: rgba(22,163,74,0.12);  color: #16a34a; }
    .sf-print-type-pill--replacement { background: rgba(234,88,12,0.12);  color: #ea580c; }
    .sf-print-type-pill--additional  { background: rgba(22,109,245,0.12); color: #166df5; }
    .sf-print-type-pill--returning   { background: rgba(124,58,237,0.12); color: #7c3aed; }
    .sf-print-type-pill--default     { background: rgba(100,116,139,0.12);color: #64748b; }
    .sf-print-footer { margin-top: 18px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 9px; color: #94a3b8; display: flex; justify-content: space-between; }
    @page { size: A4 landscape; margin: 15mm; }
}
</style>

@php
    $metrics   = $this->getMetrics();
    $chartData = $this->getFlowChartData();
    $summary   = $this->getIssuanceSummary();
    $maxTotal  = collect($summary)->max('total') ?: 1;

    // Human-readable filter labels for print header
    $filterLabel = collect([
        $this->category_id ? ('Category: ' . (\App\Models\UniformCategory::find($this->category_id)?->uniform_category_name ?? $this->category_id)) : null,
        $this->item_id     ? ('Item: '     . (\App\Models\UniformItems::find($this->item_id)?->uniform_item_name ?? $this->item_id))                     : null,
        $this->variant_id  ? ('Variant: '  . (\App\Models\UniformItemVariants::find($this->variant_id)?->uniform_item_size ?? $this->variant_id))        : null,
        'Period: ' . \Carbon\Carbon::parse($this->date_from)->format('M d, Y') . ' – ' . \Carbon\Carbon::parse($this->date_to)->format('M d, Y'),
    ])->filter()->implode(' | ');

    $tabLabel = match($this->summary_tab) {
        'item'   => 'By Item',
        'site'   => 'By Site',
        'person' => 'By Person',
        default  => 'Summary',
    };
@endphp

{{-- ══════════════════════════════════════════════════════
     HIDDEN PRINT AREA (only rendered to DOM, printed via JS)
═══════════════════════════════════════════════════════ --}}
<div id="sf-print-area" style="display:none;">
    <div class="sf-print-header">
        <h1>Uniform Stock Flow — Issuance Summary ({{ $tabLabel }})</h1>
        <p>{{ $filterLabel }} &nbsp;|&nbsp; Generated: {{ now()->format('M d, Y H:i') }}</p>
    </div>

    {{-- KPIs --}}
    <div class="sf-print-kpis">
        <div class="sf-print-kpi sf-print-kpi--in">
            <div class="sf-print-kpi-label">Stock In</div>
            <div class="sf-print-kpi-value">{{ number_format($metrics['total_in']) }}</div>
        </div>
        <div class="sf-print-kpi sf-print-kpi--out">
            <div class="sf-print-kpi-label">Stock Out</div>
            <div class="sf-print-kpi-value">{{ number_format($metrics['total_out']) }}</div>
        </div>
        <div class="sf-print-kpi sf-print-kpi--net">
            <div class="sf-print-kpi-label">Net Movement</div>
            <div class="sf-print-kpi-value">{{ ($metrics['net'] >= 0 ? '+' : '') . number_format($metrics['net']) }}</div>
        </div>
        <div class="sf-print-kpi sf-print-kpi--stock">
            <div class="sf-print-kpi-label">Current Stock</div>
            <div class="sf-print-kpi-value">{{ number_format($metrics['current_stock']) }}</div>
        </div>
    </div>

    {{-- Chart placeholder (filled by JS from canvas) --}}
    <div class="sf-print-chart-wrap">
        <h2>Stock Flow Trend</h2>
        <img id="sf-print-chart-img" class="sf-print-chart-img" src="" alt="Stock Flow Chart">
    </div>

    {{-- Summary table --}}
    <div class="sf-print-table-wrap">
        <h2>Issuance Summary — {{ $tabLabel }}</h2>
        <table class="sf-print-table">
            <thead>
                <tr>
                    <th style="width:28px;">#</th>
                    <th>
                        @if($summary_tab === 'item') Item
                        @elseif($summary_tab === 'site') Site
                        @else Person
                        @endif
                    </th>
                    {{-- ✅ Single merged column matching the screen layout --}}
                    <th>Issuance Type &amp; Breakdown</th>
                    <th style="width:70px; text-align:right;">Total Qty</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summary as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            <strong>{{ $row['label'] }}</strong>
                            @if(!empty($row['category'])) <br><span style="color:#7f96b6;font-size:9px;">{{ $row['category'] }}</span> @endif
                            @if(!empty($row['site']))     <br><span style="color:#7f96b6;font-size:9px;">{{ $row['site'] }}</span>     @endif
                        </td>
                        <td>
                            @if(!empty($row['issuance_types']))
                                @foreach($row['issuance_types'] as $type => $typeData)
                                    @php
                                        $typeKey     = strtolower(str_replace([' ','-'], '_', $type ?? 'other'));
                                        $pillarClass = in_array($typeKey, ['new','replacement','additional','returning'])
                                            ? "sf-print-type-pill--{$typeKey}"
                                            : 'sf-print-type-pill--default';
                                        $subtotal    = $typeData['subtotal'] ?? 0;
                                        $sizes       = $typeData['sizes']    ?? ($typeData['items'] ?? []);
                                    @endphp
                                    {{-- Type label + subtotal --}}
                                    <div style="margin-bottom:4px;">
                                        <span class="sf-print-type-pill {{ $pillarClass }}">
                                            {{ ucwords(str_replace('_', ' ', $type ?? 'N/A')) }}
                                        </span>
                                        <strong style="margin-left:4px;font-size:10px;">{{ number_format($subtotal) }}</strong>
                                        {{-- Sizes/items indented under this type --}}
                                        @if(!empty($sizes))
                                            <div style="padding-left:8px;border-left:2px solid #e2e8f0;margin-top:2px;">
                                                @foreach($sizes as $sizeLabel => $sizeQty)
                                                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:9px;color:#64748b;">
                                                        <span>{{ $sizeLabel ?: '—' }}</span>
                                                        <span style="font-weight:700;color:#0e1b34;">{{ number_format($sizeQty) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="sf-print-badge">{{ number_format($row['total']) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px;">No data for selected period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="sf-print-footer">
        <span>Uniform Stock Flow Dashboard</span>
        <span>{{ $filterLabel }}</span>
    </div>
</div>

<div class="sf-wrap">

    {{-- ══ FILTER PANEL ══ --}}
    <div class="sf-panel">
        <div class="sf-header">
            <div>
                <div class="sf-header-title">Stock Flow Dashboard</div>
                <div class="sf-header-sub">Monitor inventory movement across all sites in real-time</div>
            </div>
        </div>
        <div class="sf-filters">
            <div class="sf-field">
                <span class="sf-label">Category</span>
                <select class="sf-select" wire:model.live="category_id">
                    <option value="">All Categories</option>
                    @foreach($this->getCategoryOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sf-field">
                <span class="sf-label">Item</span>
                <select class="sf-select" wire:model.live="item_id">
                    <option value="">All Items</option>
                    @foreach($this->getItemOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sf-field">
                <span class="sf-label">Variant / Size</span>
                <select class="sf-select" wire:model.live="variant_id">
                    <option value="">All Variants</option>
                    @foreach($this->getVariantOptions() as $id => $size)
                        <option value="{{ $id }}">{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sf-field">
                <span class="sf-label">Date From</span>
                <input type="date" class="sf-date" wire:model.live.debounce.600ms="date_from">
            </div>
            <div class="sf-field">
                <span class="sf-label">Date To</span>
                <input type="date" class="sf-date" wire:model.live.debounce.600ms="date_to">
            </div>
            <div class="sf-reset-col" style="display:flex;align-items:flex-end;">
                <button wire:click="resetFlowFilters" class="sf-reset-btn">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ══ KPI ROW ══ --}}
    <div class="sf-kpi-grid">
        <div class="sf-kpi sf-kpi--in">
            <div class="sf-kpi-icon">↑</div>
            <div class="sf-kpi-label">Stock In</div>
            <div class="sf-kpi-value">{{ number_format($metrics['total_in']) }}</div>
        </div>
        <div class="sf-kpi sf-kpi--out">
            <div class="sf-kpi-icon">↓</div>
            <div class="sf-kpi-label">Stock Out</div>
            <div class="sf-kpi-value">{{ number_format($metrics['total_out']) }}</div>
        </div>
        <div class="sf-kpi sf-kpi--net">
            <div class="sf-kpi-icon">⇅</div>
            <div class="sf-kpi-label">Net Movement</div>
            <div class="sf-kpi-value">{{ ($metrics['net'] >= 0 ? '+' : '') . number_format($metrics['net']) }}</div>
        </div>
        <div class="sf-kpi sf-kpi--stock">
            <div class="sf-kpi-icon">▣</div>
            <div class="sf-kpi-label">Current Stock</div>
            <div class="sf-kpi-value">{{ number_format($metrics['current_stock']) }}</div>
        </div>
    </div>

    {{-- ══ CHART ══ --}}
    <div class="sf-panel">
        <div class="sf-header">
            <div>
                <div class="sf-header-title">Stock Flow Trend</div>
                <div class="sf-header-sub">Monthly in / out / net movement</div>
            </div>
            <div style="display:flex;gap:14px;align-items:center;">
                <span style="font-size:11px;display:flex;align-items:center;gap:5px;color:#16a34a;font-weight:600;">
                    <span style="width:18px;height:2px;background:#16a34a;display:inline-block;border-radius:2px;"></span>In
                </span>
                <span style="font-size:11px;display:flex;align-items:center;gap:5px;color:#dc2626;font-weight:600;">
                    <span style="width:18px;height:2px;background:#dc2626;display:inline-block;border-radius:2px;"></span>Out
                </span>
                <span style="font-size:11px;display:flex;align-items:center;gap:5px;color:#7c3aed;font-weight:600;">
                    <span style="width:18px;height:2px;background:#7c3aed;display:inline-block;border-radius:2px;border-style:dashed;"></span>Net
                </span>
            </div>
        </div>

        {{-- ✅ wire:ignore keeps canvas safe from Livewire DOM morphing --}}
        <div wire:ignore class="sf-chart-body">
            <canvas id="sfFlowChart"></canvas>
        </div>
    </div>

    {{-- ══ ISSUANCE SUMMARY ══ --}}
    <div class="sf-panel">
        <div class="sf-header">
            <div>
                <div class="sf-header-title">Issuance Summary</div>
                <div class="sf-header-sub">Breakdown of issued uniforms by item, site, and recipient</div>
            </div>
            {{-- ✅ Print button — prints what's currently filtered/tabbed --}}
            <button onclick="sfPrintSummary()" class="sf-print-btn">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print Report
            </button>
        </div>

        <div class="sf-tabs">
            <button wire:click="setSummaryTab('item')"
                class="sf-tab {{ $summary_tab === 'item' ? 'sf-tab--active' : '' }}">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                By Item
            </button>
            <button wire:click="setSummaryTab('site')"
                class="sf-tab {{ $summary_tab === 'site' ? 'sf-tab--active' : '' }}">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                By Site
            </button>
            <button wire:click="setSummaryTab('person')"
                class="sf-tab {{ $summary_tab === 'person' ? 'sf-tab--active' : '' }}">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg>
                By Person
            </button>
        </div>

        <div class="sf-summary-body">
            <table class="sf-table">
                <thead>
                    <tr>
                        <th style="width:32px;">#</th>
                        <th>
                            @if($summary_tab === 'item') Item
                            @elseif($summary_tab === 'site') Site
                            @else Person
                            @endif
                        </th>
                        {{-- ✅ Single merged column: Issuance Type → Sizes nested inside --}}
                        <th>Issuance Type &amp; Breakdown</th>
                        <th style="width:80px;">Total Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary as $i => $row)
                        <tr>
                            <td><span class="sf-td-rank">{{ $i + 1 }}</span></td>
                            <td>
                                <div style="font-weight:600;font-size:12.5px;">{{ $row['label'] }}</div>
                                @if(!empty($row['category']))
                                    <div class="sf-td-meta">{{ $row['category'] }}</div>
                                @elseif(!empty($row['site']))
                                    <div class="sf-td-meta">{{ $row['site'] }}</div>
                                @endif
                                <div style="margin-top:5px;">
                                    <div class="sf-bar-wrap" style="width:120px;">
                                        <div class="sf-bar" style="width:{{ min(100, round($row['total'] / $maxTotal * 100)) }}%"></div>
                                    </div>
                                </div>
                            </td>

                            {{-- ✅ Issuance type → its sizes/items nested --}}
                            <td>
                                @if(!empty($row['issuance_types']))
                                    @foreach($row['issuance_types'] as $type => $typeData)
                                        @php
                                            $typeKey   = strtolower(str_replace([' ','-'], '_', $type ?? 'other'));
                                            $pillClass = in_array($typeKey, ['new','replacement','additional','returning'])
                                                ? "sf-type-pill--{$typeKey}"
                                                : 'sf-type-pill--default';
                                            $subtotal  = $typeData['subtotal'] ?? 0;
                                            $sizes     = $typeData['sizes']    ?? ($typeData['items'] ?? []);
                                        @endphp
                                        <div class="sf-type-block">
                                            {{-- Type pill + subtotal --}}
                                            <div class="sf-type-block-header">
                                                <span class="sf-type-pill {{ $pillClass }}">
                                                    {{ ucwords(str_replace('_', ' ', $type ?? 'N/A')) }}
                                                </span>
                                                <span style="font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;color:var(--cl-text,#0e1b34);">
                                                    {{ number_format($subtotal) }}
                                                </span>
                                            </div>
                                            {{-- Sizes / items under this type --}}
                                            @if(!empty($sizes))
                                                <div class="sf-type-sizes">
                                                    @foreach($sizes as $sizeLabel => $sizeQty)
                                                        <div class="sf-type-size-row">
                                                            <span>{{ $sizeLabel ?: '—' }}</span>
                                                            <span class="sf-type-size-qty">{{ number_format($sizeQty) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <span class="sf-td-meta">—</span>
                                @endif
                            </td>

                            <td><span class="sf-badge sf-badge--blue">{{ number_format($row['total']) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="sf-empty">No issuance data for the selected period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    let sfChart = null;

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function buildChart(data) {
        const el = document.getElementById('sfFlowChart');
        if (!el) return;

        data = data || { labels: [], inData: [], outData: [], netData: [] };

        const dark  = isDark();
        const grid  = dark ? 'rgba(22,109,245,0.10)' : 'rgba(22,109,245,0.07)';
        const ticks = dark ? '#4d7ab5' : '#7f96b6';

        if (sfChart) {
            sfChart.destroy();
            sfChart = null;
        }

        sfChart = new Chart(el, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'In', data: data.inData,
                        borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.08)',
                        tension: 0.38, fill: true, borderWidth: 2,
                        pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#16a34a',
                    },
                    {
                        label: 'Out', data: data.outData,
                        borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.06)',
                        tension: 0.38, fill: true, borderWidth: 2,
                        pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#dc2626',
                    },
                    {
                        label: 'Net', data: data.netData,
                        borderColor: '#7c3aed', backgroundColor: 'transparent',
                        borderDash: [6, 4], tension: 0.38, fill: false, borderWidth: 2,
                        pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: '#7c3aed',
                    },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: dark ? '#111827' : '#fff',
                        borderColor: 'rgba(22,109,245,0.2)', borderWidth: 1,
                        titleColor: dark ? '#c8d8f0' : '#0e1b34',
                        bodyColor: dark ? '#94a3b8' : '#445a7a',
                        padding: 10, cornerRadius: 10,
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()}`
                        }
                    }
                },
                scales: {
                    x: { grid: { color: grid }, ticks: { color: ticks, font: { family: 'JetBrains Mono', size: 11 } } },
                    y: { grid: { color: grid }, ticks: { color: ticks, font: { family: 'JetBrains Mono', size: 11 } } }
                }
            }
        });
    }

    function updateChartData(data) {
        if (!sfChart) {
            buildChart(data);
            return;
        }
        sfChart.data.labels           = data.labels;
        sfChart.data.datasets[0].data = data.inData;
        sfChart.data.datasets[1].data = data.outData;
        sfChart.data.datasets[2].data = data.netData;
        sfChart.update('active');
    }

    // ── Print function ────────────────────────────────────────────────────────
    window.sfPrintSummary = function () {
        // Inject the chart as a base64 PNG into the print area before printing
        const canvas = document.getElementById('sfFlowChart');
        const printImg = document.getElementById('sf-print-chart-img');
        if (canvas && printImg) {
            // Render chart on a white background for clean printing
            const offscreen = document.createElement('canvas');
            offscreen.width  = canvas.width;
            offscreen.height = canvas.height;
            const ctx = offscreen.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, offscreen.width, offscreen.height);
            ctx.drawImage(canvas, 0, 0);
            printImg.src = offscreen.toDataURL('image/png');
        }

        // Show the print area, trigger print, then hide again
        const area = document.getElementById('sf-print-area');
        area.style.display = 'block';
        setTimeout(() => {
            window.print();
            // Hide after print dialog closes (small delay for Safari)
            setTimeout(() => { area.style.display = 'none'; }, 500);
        }, 120);
    };

    // Initial build on page load
    document.addEventListener('DOMContentLoaded', () => buildChart(@json($chartData)));
    document.addEventListener('livewire:navigated', () => buildChart(@json($chartData)));

    // ✅ Listen for Livewire dispatch event — PHP sends fresh data directly here
    document.addEventListener('sf-chart-update', (e) => {
        updateChartData(e.detail);
    });

    // Dark mode toggle → full rebuild
    const obs = new MutationObserver(() => {
        if (sfChart) buildChart({
            labels: sfChart.data.labels,
            inData: sfChart.data.datasets[0].data,
            outData: sfChart.data.datasets[1].data,
            netData: sfChart.data.datasets[2].data,
        });
    });
    obs.observe(document.documentElement, { attributeFilter: ['class'] });
})();
</script>
@endpush

</x-filament-panels::page>