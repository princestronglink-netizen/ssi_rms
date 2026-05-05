<x-filament-panels::page>

<style>
/* ================================================================
   STOCK FLOW DASHBOARD — scoped styles
================================================================ */

@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap');
.sf-report-check-box svg { opacity: 0; }
.sf-report-check.sf-check--active .sf-report-check-box svg { opacity: 1; }
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

.sf-header-title { font-size:13px; font-weight:700; color:var(--cl-text,#0e1b34); letter-spacing:-0.01em; font-family:'Syne',sans-serif; }
.sf-header-sub   { font-size:11px; color:var(--cl-text-3,#7f96b6); margin-top:2px; font-family:'DM Sans',sans-serif; }

/* ── Main dashboard filter row ── */
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
    font-family:'IBM Plex Mono',monospace;
}

.sf-select, .sf-date {
    width:100%; padding:8px 28px 8px 10px; font-size:12.5px;
    font-family:'DM Sans',sans-serif; font-weight:500; border-radius:10px;
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
    font-family:'DM Sans',sans-serif;
}
.sf-reset-btn:hover { background:rgba(22,109,245,0.08); }

/* ── KPIs ────────────────────────────────────────────────────────── */
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

.sf-kpi-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--cl-text-3,#7f96b6); font-family:'IBM Plex Mono',monospace; }
.sf-kpi-value { font-size:28px; font-weight:700; font-family:'IBM Plex Mono',monospace; margin-top:4px; line-height:1.1; }
.sf-kpi-sub   { font-size:11px; color:var(--cl-text-3,#7f96b6); margin-top:3px; font-family:'DM Sans',sans-serif; }
.sf-kpi--in    .sf-kpi-value { color:#16a34a; }
.sf-kpi--out   .sf-kpi-value { color:#dc2626; }
.sf-kpi--net   .sf-kpi-value { color:#7c3aed; }
.sf-kpi--stock .sf-kpi-value { color:#0369a1; }

/* ── Chart ───────────────────────────────────────────────────────── */
.sf-chart-body { padding:14px 20px 20px; height:310px; }

/* ── Tabs ────────────────────────────────────────────────────────── */
.sf-tabs {
    display:flex; gap:0;
    border-bottom:1px solid var(--cl-border,rgba(22,109,245,0.12));
    padding:0 20px; margin-top:0;
}
.sf-tab {
    padding:10px 16px; font-size:12px; font-weight:600;
    color:var(--cl-text-3,#7f96b6); border:none;
    border-bottom:2px solid transparent; background:transparent;
    cursor:pointer; margin-bottom:-1px;
    transition:color .18s, border-color .18s;
    display:flex; align-items:center; gap:6px;
    font-family:'DM Sans',sans-serif;
}
.sf-tab:hover { color:rgb(22,109,245); }
.sf-tab--active { color:rgb(22,109,245); border-bottom-color:rgb(22,109,245); }

/* ═══════════════════════════════════════════════════════════════════
   ISSUANCE SUMMARY — dedicated independent filter section
═══════════════════════════════════════════════════════════════════ */

/* The filter zone strip — visually distinct from panel header */
.sf-summary-filter-zone {
    margin: 0 0 0 0;
    border-top: 1px solid var(--cl-border, rgba(22,109,245,0.10));
    border-bottom: 1px solid var(--cl-border, rgba(22,109,245,0.10));
    background: linear-gradient(to bottom, rgba(22,109,245,0.035), rgba(22,109,245,0.015));
    padding: 14px 20px 12px;
}
.dark .sf-summary-filter-zone {
    background: linear-gradient(to bottom, rgba(22,109,245,0.07), rgba(22,109,245,0.03));
}

/* Filter zone label/title row */
.sf-summary-filter-zone-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}
.sf-summary-filter-zone-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgb(22,109,245);
    font-family: 'IBM Plex Mono', monospace;
    display: flex;
    align-items: center;
    gap: 6px;
}
.sf-summary-filter-zone-label::before {
    content: '';
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: rgb(22,109,245);
    box-shadow: 0 0 0 2px rgba(22,109,245,0.2);
}
.sf-summary-filter-zone-sep {
    flex: 1;
    height: 1px;
    background: rgba(22,109,245,0.15);
}
.sf-summary-filter-scope-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    font-family: 'IBM Plex Mono', monospace;
    background: rgba(22,109,245,0.1);
    color: rgb(22,109,245);
    border: 1px solid rgba(22,109,245,0.2);
    white-space: nowrap;
}

/* Filter grid inside summary zone */
.sf-summary-filter-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr) auto;
    gap: 10px;
    align-items: end;
}
@media(max-width:1100px){
    .sf-summary-filter-grid { grid-template-columns: repeat(3, 1fr); }
    .sf-summary-reset-col   { grid-column: 1 / -1; }
}
@media(max-width:640px){
    .sf-summary-filter-grid { grid-template-columns: 1fr 1fr; }
}

/* Summary-scoped selects — slightly different tint to reinforce independence */
.sf-summary-select, .sf-summary-date {
    width:100%; padding:8px 28px 8px 10px; font-size:12.5px;
    font-family:'DM Sans',sans-serif; font-weight:500; border-radius:10px;
    border:1px solid rgba(22,109,245,0.2);
    background: rgba(22,109,245,0.04); color:var(--cl-text,#0e1b34);
    transition:border-color .18s, box-shadow .18s;
    appearance:none; -webkit-appearance:none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%231669f5' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 10px center;
    background-color: rgba(22,109,245,0.04);
}
.sf-summary-date {
    background-image: none;
    padding-right: 10px;
}
.sf-summary-select:focus, .sf-summary-date:focus {
    outline:none; border-color:rgba(22,109,245,0.55);
    box-shadow:0 0 0 3px rgba(22,109,245,0.15);
}
.dark .sf-summary-select, .dark .sf-summary-date {
    background-color: rgba(22,109,245,0.08);
    color: var(--cl-text,#e8edf7);
    border-color: rgba(22,109,245,0.25);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%234d8cf5' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
}
.dark .sf-summary-date { background-image: none; }

/* Active filter badges row */
.sf-summary-active-badges {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed rgba(22,109,245,0.15);
}
.sf-summary-active-badges-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--cl-text-3, #7f96b6);
    font-family: 'IBM Plex Mono', monospace;
    margin-right: 2px;
    white-space: nowrap;
}
.sf-filter-active-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 6px;
    font-size: 10.5px; font-weight: 600;
    background: rgba(22,109,245,0.1); color: rgb(22,109,245);
    border: 1px solid rgba(22,109,245,0.2);
    font-family: 'IBM Plex Mono', monospace;
}
.sf-filter-active-badge button {
    background: none; border: none; cursor: pointer;
    color: inherit; padding: 0; line-height: 1;
    font-size: 12px; margin-left: 2px; opacity: 0.6;
}
.sf-filter-active-badge button:hover { opacity: 1; }

/* ── Summary Table ───────────────────────────────────────────────── */
.sf-summary-body { padding: 16px 20px 20px; }

.sf-table { width:100%; border-collapse:collapse; }
.sf-table thead th {
    font-size:10px; font-weight:700; text-transform:uppercase;
    letter-spacing:0.08em; color:var(--cl-text-3,#7f96b6);
    padding:7px 8px; text-align:left;
    background:var(--cl-tinted,#f8fbff);
    border-bottom:1px solid var(--cl-border,rgba(22,109,245,0.12));
    font-family:'IBM Plex Mono',monospace;
}
.dark .sf-table thead th { background:var(--cl-sunken,#0d1220); }
.sf-table thead th:last-child { text-align:right; }
.sf-table tbody tr { transition:background .13s; }
.sf-table tbody tr:hover { background:rgba(22,109,245,0.04); }
.sf-table tbody td {
    padding:9px 8px;
    border-bottom:1px solid var(--cl-border,rgba(22,109,245,0.08));
    font-size:12.5px; color:var(--cl-text,#0e1b34);
    font-family:'DM Sans',sans-serif;
}
.sf-table tbody td:last-child {
    text-align:right; font-family:'IBM Plex Mono',monospace;
    font-size:12px; font-weight:600;
}
.sf-td-meta { font-size:11px; color:var(--cl-text-3,#7f96b6); font-family:'DM Sans',sans-serif; }
.sf-td-rank {
    display:inline-flex; align-items:center; justify-content:center;
    width:22px; height:22px; border-radius:6px;
    background:rgba(22,109,245,0.08); color:rgb(22,109,245);
    font-size:10px; font-weight:700; font-family:'IBM Plex Mono',monospace;
    margin-right:6px; flex-shrink:0;
}
.sf-badge {
    display:inline-flex; align-items:center;
    padding:3px 10px; border-radius:99px;
    font-size:11px; font-weight:700; font-family:'IBM Plex Mono',monospace;
}
.sf-badge--blue   { background:rgba(22,109,245,0.1);  color:rgb(22,109,245); }
.sf-badge--green  { background:rgba(22,163,74,0.1);   color:#16a34a; }
.sf-badge--orange { background:rgba(234,88,12,0.1);   color:#ea580c; }
.sf-badge--purple { background:rgba(124,58,237,0.1);  color:#7c3aed; }
.sf-badge--gray   { background:rgba(100,116,139,0.1); color:#64748b; }

/* ── Issuance Type Pills ──────────────────────────────────────────── */
.sf-type-pill {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 8px; border-radius:6px;
    font-size:10px; font-weight:700; font-family:'IBM Plex Mono',monospace;
    white-space:nowrap; letter-spacing:0.03em;
}
.sf-type-pill::before { content:''; width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.sf-type-pill--new::before         { background:#16a34a; }
.sf-type-pill--replacement::before { background:#ea580c; }
.sf-type-pill--additional::before  { background:rgb(22,109,245); }
.sf-type-pill--returning::before   { background:#7c3aed; }
.sf-type-pill--default::before     { background:#64748b; }
.sf-type-pill--new         { background:rgba(22,163,74,0.1);   color:#16a34a; }
.sf-type-pill--replacement { background:rgba(234,88,12,0.1);   color:#ea580c; }
.sf-type-pill--additional  { background:rgba(22,109,245,0.1);  color:rgb(22,109,245); }
.sf-type-pill--returning   { background:rgba(124,58,237,0.1);  color:#7c3aed; }
.sf-type-pill--default     { background:rgba(100,116,139,0.1); color:#64748b; }

.sf-issuance-types-wrap { display:flex; flex-direction:column; gap:5px; }
.sf-type-block {
    border:1px solid var(--cl-border,rgba(22,109,245,0.1));
    border-radius:10px; padding:8px 10px;
    background:var(--cl-tinted,#f8fbff);
}
.dark .sf-type-block { background:rgba(22,109,245,0.04); }
.sf-type-block-header {
    display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:5px;
}
.sf-type-block-total {
    font-family:'IBM Plex Mono',monospace; font-size:13px; font-weight:700;
    background:rgba(22,109,245,0.08); color:rgb(22,109,245);
    padding:1px 8px; border-radius:6px;
}
.sf-size-list { display:flex; flex-direction:column; gap:2px; padding-left:4px; }
.sf-size-row  {
    display:flex; align-items:center; justify-content:space-between;
    gap:12px; padding:2px 4px; border-radius:5px;
}
.sf-size-row:hover { background:rgba(22,109,245,0.05); }
.sf-size-name { font-size:11.5px; color:var(--cl-text-3,#7f96b6); font-family:'DM Sans',sans-serif; min-width:40px; }
.sf-size-qty  { font-family:'IBM Plex Mono',monospace; font-size:12px; font-weight:700; color:var(--cl-text,#0e1b34); }

.sf-empty { text-align:center; color:var(--cl-text-3,#7f96b6); font-size:12px; padding:28px 0; font-family:'DM Sans',sans-serif; }

/* ── Chart legend pills ──────────────────────────────────────────── */
.sf-legend { display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
.sf-legend-item {
    display:flex; align-items:center; gap:6px;
    font-size:11px; font-weight:600; font-family:'IBM Plex Mono',monospace;
}
.sf-legend-line { width:20px; height:3px; border-radius:2px; display:inline-block; }
.sf-legend-line--dashed { border-top:2px dashed; background:transparent !important; height:0; }

/* ── Generate Report Button ──────────────────────────────────────── */
.sf-gen-report-wrap { display:flex; justify-content:flex-end; padding: 4px 0 8px; }
.sf-gen-report-btn {
    display:inline-flex; align-items:center; gap:8px;
    padding:11px 22px; font-size:13px; font-weight:700;
    border-radius:12px;
    background: linear-gradient(135deg, rgb(22,109,245) 0%, #0ea5e9 100%);
    color:#fff; border:none; cursor:pointer;
    box-shadow: 0 4px 14px rgba(22,109,245,0.35);
    transition: transform .18s, box-shadow .18s, filter .18s;
    font-family:'Syne',sans-serif; letter-spacing:-0.01em;
    position:relative; overflow:hidden;
}
.sf-gen-report-btn::before {
    content:''; position:absolute; inset:0;
    background:linear-gradient(135deg,rgba(255,255,255,0.15) 0%,transparent 60%);
    pointer-events:none;
}
.sf-gen-report-btn:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(22,109,245,0.45); filter:brightness(1.05); }
.sf-gen-report-btn:active { transform:translateY(0); }

/* ── Modal overlay ───────────────────────────────────────────────── */
.sf-modal-overlay {
    position:fixed; inset:0; z-index:9999;
    background:rgba(9,16,32,0.65); backdrop-filter:blur(6px);
    display:flex; align-items:center; justify-content:center; padding:20px;
    opacity:0; pointer-events:none; transition:opacity .25s ease;
}
.sf-modal-overlay.sf-modal--open { opacity:1; pointer-events:all; }
.sf-modal {
    background:var(--cl-base,#fff);
    border:1px solid var(--cl-border,rgba(22,109,245,0.15));
    border-radius:20px;
    box-shadow: 0 30px 80px rgba(9,16,32,0.3);
    width:100%; max-width:800px; max-height:88vh; overflow-y:auto;
    transform:translateY(28px) scale(0.97);
    transition:transform .28s cubic-bezier(.34,1.56,.64,1);
}
.sf-modal-overlay.sf-modal--open .sf-modal { transform:translateY(0) scale(1); }
.sf-modal-header {
    display:flex; align-items:flex-start; justify-content:space-between;
    padding:22px 24px 16px; gap:12px;
    border-bottom:1px solid var(--cl-border,rgba(22,109,245,0.1));
    position:sticky; top:0; background:var(--cl-base,#fff);
    z-index:10; border-radius:20px 20px 0 0;
}
.dark .sf-modal-header { background:var(--cl-base,#0d1220); }
.sf-modal-title { font-size:16px; font-weight:800; color:var(--cl-text,#0e1b34); font-family:'Syne',sans-serif; letter-spacing:-0.02em; }
.sf-modal-sub   { font-size:12px; color:var(--cl-text-3,#7f96b6); margin-top:2px; font-family:'DM Sans',sans-serif; }
.sf-modal-close {
    width:30px; height:30px; border-radius:8px;
    border:1px solid var(--cl-border,rgba(22,109,245,0.15));
    background:transparent; color:var(--cl-text-3,#7f96b6);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:background .15s, color .15s; flex-shrink:0;
    font-size:16px; font-weight:400;
}
.sf-modal-close:hover { background:rgba(220,38,38,0.08); color:#dc2626; border-color:rgba(220,38,38,0.2); }
.sf-modal-body { padding:20px 24px; display:flex; flex-direction:column; gap:20px; }
.sf-modal-section { display:flex; flex-direction:column; gap:8px; }
.sf-modal-section-title {
    font-size:10px; font-weight:700; text-transform:uppercase;
    letter-spacing:0.1em; color:var(--cl-text-3,#7f96b6);
    font-family:'IBM Plex Mono',monospace; display:flex; align-items:center; gap:6px;
}
.sf-modal-section-title::after { content:''; flex:1; height:1px; background:var(--cl-border,rgba(22,109,245,0.1)); }
.sf-date-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.sf-report-checkboxes { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
@media(max-width:480px){ .sf-report-checkboxes{ grid-template-columns:1fr; } }
.sf-report-check {
    display:flex; align-items:center; gap:10px; padding:11px 14px;
    border:1px solid var(--cl-border,rgba(22,109,245,0.12)); border-radius:10px;
    cursor:pointer; background:var(--cl-tinted,#f8fbff);
    transition:border-color .15s, background .15s; user-select:none;
}
.sf-report-check:hover { border-color:rgba(22,109,245,0.35); background:rgba(22,109,245,0.04); }
.sf-report-check input[type=checkbox] { display:none; }
.sf-report-check-box {
    width:16px; height:16px; border-radius:5px;
    border:2px solid var(--cl-border-strong,rgba(22,109,245,0.3));
    background:transparent; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    transition:background .15s, border-color .15s;
}
.sf-report-check.sf-check--active .sf-report-check-box { background:rgb(22,109,245); border-color:rgb(22,109,245); }
.sf-report-check.sf-check--active { border-color:rgba(22,109,245,0.4); background:rgba(22,109,245,0.06); }
.sf-report-check-label { font-size:12.5px; font-weight:600; color:var(--cl-text,#0e1b34); font-family:'DM Sans',sans-serif; }
.sf-report-check-desc  { font-size:11px; color:var(--cl-text-3,#7f96b6); font-family:'DM Sans',sans-serif; }
.sf-report-preview { border:1px solid var(--cl-border,rgba(22,109,245,0.12)); border-radius:12px; overflow:hidden; }
.sf-report-preview-header { background:linear-gradient(135deg,rgb(22,109,245) 0%,#0ea5e9 100%); padding:14px 18px; display:flex; align-items:center; gap:10px; }
.sf-report-preview-title { font-size:12px; font-weight:700; color:#fff; font-family:'Syne',sans-serif; }
.sf-report-preview-sub   { font-size:10px; color:rgba(255,255,255,0.75); font-family:'DM Sans',sans-serif; }
.sf-report-sections { padding:14px 18px; display:flex; flex-direction:column; gap:10px; background:var(--cl-tinted,#f8fbff); }
.dark .sf-report-sections { background:rgba(22,109,245,0.03); }
.sf-report-section-chip {
    display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:7px;
    font-size:11px; font-weight:600; font-family:'IBM Plex Mono',monospace;
    background:var(--cl-base,#fff); border:1px solid var(--cl-border,rgba(22,109,245,0.15)); color:var(--cl-text,#0e1b34);
}
.sf-report-section-chip.sf-chip--active { background:rgba(22,109,245,0.1); color:rgb(22,109,245); border-color:rgba(22,109,245,0.25); }
.sf-modal-footer {
    padding:16px 24px 20px; border-top:1px solid var(--cl-border,rgba(22,109,245,0.1));
    display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
}
.sf-modal-cancel {
    padding:9px 18px; font-size:12.5px; font-weight:600; border-radius:10px;
    border:1px solid var(--cl-border-strong,rgba(22,109,245,0.2));
    background:transparent; color:var(--cl-text-3,#7f96b6); cursor:pointer;
    transition:background .15s, color .15s; font-family:'DM Sans',sans-serif;
}
.sf-modal-cancel:hover { background:rgba(22,109,245,0.05); color:var(--cl-text,#0e1b34); }
.sf-modal-generate {
    display:inline-flex; align-items:center; gap:7px; padding:9px 22px;
    font-size:13px; font-weight:700; border-radius:10px;
    background:linear-gradient(135deg,rgb(22,109,245) 0%,#0ea5e9 100%);
    color:#fff; border:none; cursor:pointer; box-shadow:0 3px 10px rgba(22,109,245,0.3);
    transition:transform .15s, box-shadow .15s; font-family:'Syne',sans-serif;
}
.sf-modal-generate:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(22,109,245,0.4); }
.sf-modal-generate:disabled { opacity:.6; cursor:not-allowed; transform:none; }
.sf-spinner {
    width:14px; height:14px; border:2px solid rgba(255,255,255,0.3);
    border-top-color:#fff; border-radius:50%;
    animation:sf-spin .6s linear infinite; display:none;
}
.sf-spinner--visible { display:block; }
@keyframes sf-spin { to { transform:rotate(360deg); } }
.dark .sf-modal { background:var(--cl-base,#0d1220); }
.dark .sf-report-check { background:rgba(22,109,245,0.04); }
.dark .sf-report-section-chip { background:rgba(255,255,255,0.05); }
</style>

@php
    $metrics   = $this->getMetrics();
    $chartData = $this->getFlowChartData();
    $summary   = $this->getIssuanceSummary();
    $maxTotal  = collect($summary)->max('total') ?: 1;

    // Summary active filter state
    $summaryActiveFilters = array_filter([$summary_category_id, $summary_item_id, $summary_variant_id]);
    $summaryDateDiff      = ($summary_date_from !== $date_from || $summary_date_to !== $date_to);
    $activeSummaryCount   = count($summaryActiveFilters) + ($summaryDateDiff ? 1 : 0);
@endphp

<div class="sf-wrap">

    {{-- ══════════════════════════════════════════════════════════════
         SECTION A — MAIN DASHBOARD (KPIs + Chart)
         Filters here affect: KPIs, Stock Flow Trend chart only
    ════════════════════════════════════════════════════════════════ --}}
    <div class="sf-panel">
        <div class="sf-header">
            <div>
                <div class="sf-header-title">Stock Flow Dashboard</div>
                <div class="sf-header-sub">Monitor inventory movement across all sites in real-time</div>
            </div>
            {{-- Scope badge clarifying what these filters affect --}}
            <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:7px;font-size:10px;font-weight:700;font-family:'IBM Plex Mono',monospace;background:rgba(22,163,74,0.1);color:#16a34a;border:1px solid rgba(22,163,74,0.2);">
                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                Affects KPIs &amp; Chart
            </span>
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
            <div class="sf-kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14m7-7-7 7-7-7"/></svg>
            </div>
            <div class="sf-kpi-label">Stock In</div>
            <div class="sf-kpi-value">{{ number_format($metrics['total_in']) }}</div>
            <div class="sf-kpi-sub">Restocks + Returns</div>
        </div>
        <div class="sf-kpi sf-kpi--out">
            <div class="sf-kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 19V5m7 7-7-7-7 7"/></svg>
            </div>
            <div class="sf-kpi-label">Stock Out</div>
            <div class="sf-kpi-value">{{ number_format($metrics['total_out']) }}</div>
            <div class="sf-kpi-sub">Total Issued</div>
        </div>
        <div class="sf-kpi sf-kpi--net">
            <div class="sf-kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m7 16 4-4-4-4m6 8 4-4-4-4"/></svg>
            </div>
            <div class="sf-kpi-label">Net Movement</div>
            <div class="sf-kpi-value">{{ ($metrics['net'] >= 0 ? '+' : '') . number_format($metrics['net']) }}</div>
            <div class="sf-kpi-sub">{{ $metrics['net'] >= 0 ? 'Surplus period' : 'Deficit period' }}</div>
        </div>
        <div class="sf-kpi sf-kpi--stock">
            <div class="sf-kpi-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2M12 12v5m-3-2.5 3 2.5 3-2.5"/></svg>
            </div>
            <div class="sf-kpi-label">Current Stock</div>
            <div class="sf-kpi-value">{{ number_format($metrics['current_stock']) }}</div>
            <div class="sf-kpi-sub">Units on hand</div>
        </div>
    </div>

    {{-- ══ CHART ══ --}}
    <div class="sf-panel">
        <div class="sf-header">
            <div>
                <div class="sf-header-title">Stock Flow Trend</div>
                <div class="sf-header-sub">Monthly in / out / net movement</div>
            </div>
            <div class="sf-legend">
                <div class="sf-legend-item" style="color:#16a34a;">
                    <span class="sf-legend-line" style="background:#16a34a;"></span>
                    Stock In
                </div>
                <div class="sf-legend-item" style="color:#dc2626;">
                    <span class="sf-legend-line" style="background:#dc2626;"></span>
                    Stock Out
                </div>
                <div class="sf-legend-item" style="color:#7c3aed;">
                    <span class="sf-legend-line sf-legend-line--dashed" style="border-color:#7c3aed; width:20px;"></span>
                    Net
                </div>
            </div>
        </div>
        <div wire:ignore class="sf-chart-body">
            <canvas id="sfFlowChart"></canvas>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         SECTION B — ISSUANCE SUMMARY
         Has its OWN independent filters (category / item / variant / dates)
         completely separate from the main dashboard filters above
    ════════════════════════════════════════════════════════════════ --}}
    <div class="sf-panel">

        {{-- Panel header --}}
        <div class="sf-header">
            <div>
                <div class="sf-header-title">Issuance Summary</div>
                <div class="sf-header-sub">Breakdown of issued uniforms by item, site, and recipient</div>
            </div>
            {{-- Summary stats --}}
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                @php $totalIssued = collect($summary)->sum('total'); @endphp
                <span class="sf-badge sf-badge--blue">
                    {{ count($summary) }} {{ $summary_tab === 'item' ? 'items' : ($summary_tab === 'site' ? 'sites' : 'people') }}
                </span>
                <span class="sf-badge sf-badge--purple">{{ number_format($totalIssued) }} total pcs</span>
            </div>
        </div>

        {{-- ── DEDICATED INDEPENDENT FILTER ZONE ── --}}
        <div class="sf-summary-filter-zone">

            {{-- Zone header --}}
            <div class="sf-summary-filter-zone-title">
                <span class="sf-summary-filter-zone-label">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                    Issuance Summary Filters
                </span>
                <span class="sf-summary-filter-zone-sep"></span>
                <span class="sf-summary-filter-scope-badge">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Affects this table only
                </span>
            </div>

            {{-- Filter controls grid --}}
            <div class="sf-summary-filter-grid">
                <div class="sf-field">
                    <span class="sf-label">Category</span>
                    <select class="sf-summary-select" wire:model.live="summary_category_id">
                        <option value="">All Categories</option>
                        @foreach($this->getCategoryOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sf-field">
                    <span class="sf-label">Item</span>
                    <select class="sf-summary-select" wire:model.live="summary_item_id">
                        <option value="">All Items</option>
                        @foreach($this->getSummaryItemOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sf-field">
                    <span class="sf-label">Variant / Size</span>
                    <select class="sf-summary-select" wire:model.live="summary_variant_id">
                        <option value="">All Variants</option>
                        @foreach($this->getSummaryVariantOptions() as $id => $size)
                            <option value="{{ $id }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sf-field">
                    <span class="sf-label">Date From</span>
                    <input type="date" class="sf-summary-date" wire:model.live.debounce.600ms="summary_date_from">
                </div>
                <div class="sf-field">
                    <span class="sf-label">Date To</span>
                    <input type="date" class="sf-summary-date" wire:model.live.debounce.600ms="summary_date_to">
                </div>
                <div class="sf-summary-reset-col" style="display:flex;align-items:flex-end;">
                    <button wire:click="resetSummaryFilters" class="sf-reset-btn">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                        Reset
                    </button>
                </div>
            </div>

            {{-- Active filter badges (only shown when filters are active) --}}
            @if($activeSummaryCount > 0)
            <div class="sf-summary-active-badges">
                <span class="sf-summary-active-badges-label">Filtering by:</span>

                @if($summary_category_id)
                    @php $catName = $this->getCategoryOptions()[$summary_category_id] ?? $summary_category_id; @endphp
                    <span class="sf-filter-active-badge">
                        Category: {{ $catName }}
                        <button wire:click="$set('summary_category_id', null)" title="Clear">×</button>
                    </span>
                @endif
                @if($summary_item_id)
                    @php $itemName = $this->getSummaryItemOptions()[$summary_item_id] ?? $summary_item_id; @endphp
                    <span class="sf-filter-active-badge">
                        Item: {{ $itemName }}
                        <button wire:click="$set('summary_item_id', null)" title="Clear">×</button>
                    </span>
                @endif
                @if($summary_variant_id)
                    @php $varName = $this->getSummaryVariantOptions()[$summary_variant_id] ?? $summary_variant_id; @endphp
                    <span class="sf-filter-active-badge">
                        Variant: {{ $varName }}
                        <button wire:click="$set('summary_variant_id', null)" title="Clear">×</button>
                    </span>
                @endif
                @if($summaryDateDiff)
                    <span class="sf-filter-active-badge">
                        {{ \Carbon\Carbon::parse($summary_date_from)->format('M d, Y') }} → {{ \Carbon\Carbon::parse($summary_date_to)->format('M d, Y') }}
                        <button wire:click="resetSummaryDates" title="Clear">×</button>
                    </span>
                @endif
            </div>
            @endif

        </div>{{-- end .sf-summary-filter-zone --}}

        {{-- Tabs (below the filter zone) --}}
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

        {{-- Summary table --}}
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
                        <th>Issuance Type Breakdown</th>
                        <th style="width:90px;">Total Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary as $i => $row)
                        <tr>
                            <td><span class="sf-td-rank">{{ $i + 1 }}</span></td>
                            <td style="min-width:140px;">
                                <div style="font-weight:600;font-size:12.5px;line-height:1.3;">{{ $row['label'] }}</div>
                                @if(!empty($row['category']))
                                    <div class="sf-td-meta">{{ $row['category'] }}</div>
                                @elseif(!empty($row['site']))
                                    <div class="sf-td-meta">{{ $row['site'] }}</div>
                                @endif
                            </td>
                            <td style="min-width:280px;">
                                @if(!empty($row['issuance_types']))
                                    <div class="sf-issuance-types-wrap">
                                        @foreach($row['issuance_types'] as $type => $typeData)
                                            @php
                                                $typeKey   = strtolower(str_replace([' ','-',' '], ['_','_','_'], trim($type ?? 'other')));
                                                $pillClass = in_array($typeKey, ['new','replacement','additional','returning'])
                                                    ? "sf-type-pill--{$typeKey}"
                                                    : 'sf-type-pill--default';
                                                $subtotal  = $typeData['subtotal'] ?? 0;
                                                $sizes     = $typeData['sizes'] ?? ($typeData['items'] ?? []);
                                            @endphp
                                            <div class="sf-type-block">
                                                <div class="sf-type-block-header">
                                                    <span class="sf-type-pill {{ $pillClass }}">
                                                        {{ ucwords(str_replace('_', ' ', $type ?? 'N/A')) }}
                                                    </span>
                                                    <span class="sf-type-block-total">{{ number_format($subtotal) }} pcs</span>
                                                </div>
                                                @if(!empty($sizes))
                                                    <div class="sf-size-list">
                                                        @foreach($sizes as $sizeLabel => $sizeQty)
                                                            <div class="sf-size-row">
                                                                <span class="sf-size-name">{{ $sizeLabel ?: '—' }}</span>
                                                                <span class="sf-size-qty">{{ number_format($sizeQty) }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="sf-td-meta">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="sf-badge sf-badge--blue">{{ number_format($row['total']) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="sf-empty">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 8px;display:block;opacity:.4;"><circle cx="12" cy="12" r="10"/><path d="M8 12h8m-4-4v8"/></svg>
                            No issuance data for the selected period.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ══ GENERATE REPORT BUTTON ══ --}}
    <div class="sf-gen-report-wrap">
        <button class="sf-gen-report-btn" id="sfOpenReportModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="12" y1="11" x2="12" y2="17"/>
                <line x1="9" y1="14" x2="15" y2="14"/>
            </svg>
            Generate Report
        </button>
    </div>

</div>

{{-- ══ REPORT MODAL ══ --}}
<div class="sf-modal-overlay" id="sfReportModal" role="dialog" aria-modal="true" aria-labelledby="sfModalTitle">
    <div class="sf-modal">
        <div class="sf-modal-header">
            <div>
                <div class="sf-modal-title" id="sfModalTitle">
                    <span style="margin-right:8px;">📊</span>Generate Stock Flow Report
                </div>
                <div class="sf-modal-sub">Configure your report parameters and select the sections to include</div>
            </div>
            <button class="sf-modal-close" id="sfCloseReportModal" aria-label="Close">✕</button>
        </div>
        <div class="sf-modal-body">
            <div class="sf-modal-section">
                <div class="sf-modal-section-title">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    Date Range
                </div>
                <div class="sf-date-row">
                    <div class="sf-field">
                        <span class="sf-label">From</span>
                        <input type="date" class="sf-date" id="sfReportDateFrom" style="border:1px solid var(--cl-border,rgba(22,109,245,0.12));padding:8px 10px;border-radius:10px;background:var(--cl-tinted,#f8fbff);color:var(--cl-text,#0e1b34);font-family:'DM Sans',sans-serif;font-size:13px;width:100%;">
                    </div>
                    <div class="sf-field">
                        <span class="sf-label">To</span>
                        <input type="date" class="sf-date" id="sfReportDateTo" style="border:1px solid var(--cl-border,rgba(22,109,245,0.12));padding:8px 10px;border-radius:10px;background:var(--cl-tinted,#f8fbff);color:var(--cl-text,#0e1b34);font-family:'DM Sans',sans-serif;font-size:13px;width:100%;">
                    </div>
                </div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">
                    <button class="sf-report-preset" data-preset="this_year"    style="padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;font-family:'IBM Plex Mono',monospace;border:1px solid var(--cl-border,rgba(22,109,245,0.15));background:transparent;color:var(--cl-text-3,#7f96b6);cursor:pointer;transition:background .13s,color .13s;">This Year</button>
                    <button class="sf-report-preset" data-preset="last_month"   style="padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;font-family:'IBM Plex Mono',monospace;border:1px solid var(--cl-border,rgba(22,109,245,0.15));background:transparent;color:var(--cl-text-3,#7f96b6);cursor:pointer;transition:background .13s,color .13s;">Last Month</button>
                    <button class="sf-report-preset" data-preset="this_quarter" style="padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;font-family:'IBM Plex Mono',monospace;border:1px solid var(--cl-border,rgba(22,109,245,0.15));background:transparent;color:var(--cl-text-3,#7f96b6);cursor:pointer;transition:background .13s,color .13s;">This Quarter</button>
                    <button class="sf-report-preset" data-preset="last_quarter" style="padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;font-family:'IBM Plex Mono',monospace;border:1px solid var(--cl-border,rgba(22,109,245,0.15));background:transparent;color:var(--cl-text-3,#7f96b6);cursor:pointer;transition:background .13s,color .13s;">Last Quarter</button>
                    <button class="sf-report-preset" data-preset="last_6months" style="padding:4px 10px;border-radius:7px;font-size:11px;font-weight:600;font-family:'IBM Plex Mono',monospace;border:1px solid var(--cl-border,rgba(22,109,245,0.15));background:transparent;color:var(--cl-text-3,#7f96b6);cursor:pointer;transition:background .13s,color .13s;">Last 6 Months</button>
                </div>
            </div>
            <div class="sf-modal-section">
                <div class="sf-modal-section-title">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Report Sections
                </div>
                <div class="sf-report-checkboxes">
                    <label class="sf-report-check sf-check--active" data-section="stock_summary">
                        <input type="checkbox" checked>
                        <div class="sf-report-check-box"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div><div class="sf-report-check-label">Current Stock</div><div class="sf-report-check-desc">Per item & variant on-hand quantities</div></div>
                    </label>
                    <label class="sf-report-check sf-check--active" data-section="issuances">
                        <input type="checkbox" checked>
                        <div class="sf-report-check-box"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div><div class="sf-report-check-label">Issuances</div><div class="sf-report-check-desc">Issued uniforms by type, item, size</div></div>
                    </label>
                    <label class="sf-report-check sf-check--active" data-section="restocks">
                        <input type="checkbox" checked>
                        <div class="sf-report-check-box"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div><div class="sf-report-check-label">Restocks</div><div class="sf-report-check-desc">Delivered restock items & quantities</div></div>
                    </label>
                    <label class="sf-report-check sf-check--active" data-section="returns">
                        <input type="checkbox" checked>
                        <div class="sf-report-check-box"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div><div class="sf-report-check-label">Returns</div><div class="sf-report-check-desc">Returned items added back to stock</div></div>
                    </label>
                    <label class="sf-report-check sf-check--active" data-section="issuance_by_site">
                        <input type="checkbox" checked>
                        <div class="sf-report-check-box"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div><div class="sf-report-check-label">By Site</div><div class="sf-report-check-desc">Issuance grouped per site location</div></div>
                    </label>
                    <label class="sf-report-check" data-section="issuance_by_person">
                        <input type="checkbox">
                        <div class="sf-report-check-box"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div><div class="sf-report-check-label">By Person</div><div class="sf-report-check-desc">Individual recipient issuance records</div></div>
                    </label>
                </div>
            </div>
            <div class="sf-modal-section">
                <div class="sf-modal-section-title">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Report Preview
                </div>
                <div class="sf-report-preview">
                    <div class="sf-report-preview-header">
                        <div>
                            <div class="sf-report-preview-title">Uniform Stock Flow Report</div>
                            <div class="sf-report-preview-sub" id="sfPreviewDateRange">Date range: —</div>
                        </div>
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="1.5" style="margin-left:auto;flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    </div>
                    <div class="sf-report-sections" id="sfPreviewChips">
                        <div style="font-size:11px;color:var(--cl-text-3,#7f96b6);font-family:'DM Sans',sans-serif;margin-bottom:4px;">Sections included:</div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;" id="sfChipList"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="sf-modal-footer">
            <div style="font-size:11px;color:var(--cl-text-3,#7f96b6);font-family:'DM Sans',sans-serif;">
                Report will be generated based on selected filters above
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <button class="sf-modal-cancel" id="sfCloseReportModal2">Cancel</button>
                <button class="sf-modal-generate" id="sfGenerateReportBtn">
                    <div class="sf-spinner" id="sfSpinner"></div>
                    <svg id="sfGenerateIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Generate &amp; Download
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    /* ─── Chart ──────────────────────────────────────────────────── */
    let sfChart = null;
    function isDark() { return document.documentElement.classList.contains('dark'); }

    function buildChart(data) {
        const el = document.getElementById('sfFlowChart');
        if (!el) return;
        data = data || { labels: [], inData: [], outData: [], netData: [] };
        const dark  = isDark();
        const grid  = dark ? 'rgba(22,109,245,0.10)' : 'rgba(22,109,245,0.07)';
        const ticks = dark ? '#4d7ab5' : '#7f96b6';
        if (sfChart) { sfChart.destroy(); sfChart = null; }
        sfChart = new Chart(el, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    { label:'In',  data:data.inData,  borderColor:'#16a34a', backgroundColor:'rgba(22,163,74,0.08)',  tension:0.38, fill:true,  borderWidth:2, pointRadius:4, pointHoverRadius:7, pointBackgroundColor:'#16a34a', pointBorderColor:'#fff', pointBorderWidth:2 },
                    { label:'Out', data:data.outData, borderColor:'#dc2626', backgroundColor:'rgba(220,38,38,0.06)',  tension:0.38, fill:true,  borderWidth:2, pointRadius:4, pointHoverRadius:7, pointBackgroundColor:'#dc2626', pointBorderColor:'#fff', pointBorderWidth:2 },
                    { label:'Net', data:data.netData, borderColor:'#7c3aed', backgroundColor:'transparent', borderDash:[6,4], tension:0.38, fill:false, borderWidth:2, pointRadius:3, pointHoverRadius:5, pointBackgroundColor:'#7c3aed', pointBorderColor:'#fff', pointBorderWidth:2 },
                ]
            },
            options: {
                responsive:true, maintainAspectRatio:false,
                interaction:{ mode:'index', intersect:false },
                plugins: {
                    legend:{ display:false },
                    tooltip:{
                        backgroundColor: dark ? '#111827' : '#fff',
                        borderColor:'rgba(22,109,245,0.2)', borderWidth:1,
                        titleColor: dark ? '#c8d8f0' : '#0e1b34',
                        bodyColor:  dark ? '#94a3b8' : '#445a7a',
                        padding:12, cornerRadius:12,
                        callbacks:{
                            title: items => items[0].label,
                            label: ctx => {
                                const sign = ctx.dataset.label === 'Net' && ctx.parsed.y >= 0 ? '+' : '';
                                return `  ${ctx.dataset.label}: ${sign}${ctx.parsed.y.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales:{
                    x:{ grid:{color:grid}, ticks:{color:ticks, font:{family:'IBM Plex Mono',size:11}} },
                    y:{ grid:{color:grid}, ticks:{color:ticks, font:{family:'IBM Plex Mono',size:11}, callback: v => v.toLocaleString()} }
                }
            }
        });
    }

    function updateChartData(data) {
        if (!sfChart) { buildChart(data); return; }
        sfChart.data.labels           = data.labels;
        sfChart.data.datasets[0].data = data.inData;
        sfChart.data.datasets[1].data = data.outData;
        sfChart.data.datasets[2].data = data.netData;
        sfChart.update('active');
    }

    document.addEventListener('DOMContentLoaded', () => buildChart(@json($chartData)));
    document.addEventListener('livewire:navigated', () => buildChart(@json($chartData)));
    document.addEventListener('sf-chart-update', (e) => updateChartData(e.detail));
    const obs = new MutationObserver(() => {
        if (sfChart) buildChart({ labels:sfChart.data.labels, inData:sfChart.data.datasets[0].data, outData:sfChart.data.datasets[1].data, netData:sfChart.data.datasets[2].data });
    });
    obs.observe(document.documentElement, { attributeFilter:['class'] });

    /* ─── Report Modal ───────────────────────────────────────────── */
    const overlay      = document.getElementById('sfReportModal');
    const openBtn      = document.getElementById('sfOpenReportModal');
    const closeBtn     = document.getElementById('sfCloseReportModal');
    const closeBtn2    = document.getElementById('sfCloseReportModal2');
    const generateBtn  = document.getElementById('sfGenerateReportBtn');
    const spinner      = document.getElementById('sfSpinner');
    const genIcon      = document.getElementById('sfGenerateIcon');
    const dateFrom     = document.getElementById('sfReportDateFrom');
    const dateTo       = document.getElementById('sfReportDateTo');
    const previewRange = document.getElementById('sfPreviewDateRange');
    const chipList     = document.getElementById('sfChipList');

    const sectionLabels = {
        stock_summary:       '📦 Current Stock',
        issuances:           '📤 Issuances',
        restocks:            '📥 Restocks',
        returns:             '🔄 Returns',
        issuance_by_site:    '🏢 By Site',
        issuance_by_person:  '👤 By Person',
    };

    function formatDate(d) {
        if (!d) return '—';
        const dt = new Date(d);
        return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
    }

    function updatePreview() {
        const f = dateFrom.value, t = dateTo.value;
        previewRange.textContent = 'Date range: ' + (f ? formatDate(f) : '—') + ' → ' + (t ? formatDate(t) : '—');
        chipList.innerHTML = '';
        document.querySelectorAll('.sf-report-check[data-section]').forEach(el => {
            const key = el.dataset.section;
            const isActive = el.classList.contains('sf-check--active');
            const chip = document.createElement('span');
            chip.className = 'sf-report-section-chip' + (isActive ? ' sf-chip--active' : '');
            chip.textContent = sectionLabels[key] || key;
            chipList.appendChild(chip);
        });
    }

    function openModal() {
        const liveDateFrom = document.querySelector('[wire\\:model\\.live\\.debounce\\.600ms="date_from"]');
        const liveDateTo   = document.querySelector('[wire\\:model\\.live\\.debounce\\.600ms="date_to"]');
        if (liveDateFrom && liveDateFrom.value) dateFrom.value = liveDateFrom.value;
        if (liveDateTo   && liveDateTo.value)   dateTo.value   = liveDateTo.value;
        if (!dateFrom.value) {
            const y = new Date().getFullYear();
            dateFrom.value = y + '-01-01';
            dateTo.value   = new Date().toISOString().split('T')[0];
        }
        updatePreview();
        overlay.classList.add('sf-modal--open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('sf-modal--open');
        document.body.style.overflow = '';
    }

    openBtn?.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    closeBtn2?.addEventListener('click', closeModal);
    overlay?.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    document.querySelectorAll('.sf-report-check[data-section], .sf-report-check[data-format]').forEach(label => {
        label.addEventListener('click', (e) => {
            e.preventDefault(); e.stopPropagation();
            const inp = label.querySelector('input');
            if (!inp) return;
            if (inp.type === 'radio') {
                document.querySelectorAll('.sf-report-check[data-format]').forEach(l => {
                    l.classList.remove('sf-check--active');
                    l.querySelector('input').checked = false;
                });
                inp.checked = true;
                label.classList.add('sf-check--active');
            } else {
                inp.checked = !inp.checked;
                label.classList.toggle('sf-check--active', inp.checked);
            }
            updatePreview();
        });
    });

    document.querySelectorAll('.sf-report-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const now = new Date();
            let f, t = now.toISOString().split('T')[0];
            const y = now.getFullYear(), m = now.getMonth();
            const preset = btn.dataset.preset;
            if (preset === 'this_year')    { f = y + '-01-01'; }
            else if (preset === 'last_month') { const lm = new Date(y, m-1, 1); f = lm.toISOString().split('T')[0]; t = new Date(y, m, 0).toISOString().split('T')[0]; }
            else if (preset === 'this_quarter') { const qStart = Math.floor(m/3)*3; f = new Date(y, qStart, 1).toISOString().split('T')[0]; }
            else if (preset === 'last_quarter') { const qStart = Math.floor(m/3)*3-3; f = new Date(y, qStart, 1).toISOString().split('T')[0]; t = new Date(y, qStart+3, 0).toISOString().split('T')[0]; }
            else if (preset === 'last_6months') { f = new Date(y, m-6, 1).toISOString().split('T')[0]; }
            dateFrom.value = f; dateTo.value = t;
            updatePreview();
            document.querySelectorAll('.sf-report-preset').forEach(b => { b.style.background=''; b.style.color='var(--cl-text-3,#7f96b6)'; b.style.borderColor='var(--cl-border,rgba(22,109,245,0.15))'; });
            btn.style.background = 'rgba(22,109,245,0.1)'; btn.style.color = 'rgb(22,109,245)'; btn.style.borderColor = 'rgba(22,109,245,0.3)';
        });
    });

    dateFrom.addEventListener('change', updatePreview);
    dateTo.addEventListener('change', updatePreview);

    generateBtn?.addEventListener('click', function () {
        const sections = Array.from(document.querySelectorAll('.sf-report-check[data-section].sf-check--active')).map(el => el.dataset.section);
        if (!dateFrom.value || !dateTo.value) { alert('Please select a date range.'); return; }
        if (!sections.length) { alert('Please select at least one report section.'); return; }
        const params = new URLSearchParams({
            date_from:   dateFrom.value,
            date_to:     dateTo.value,
            sections:    sections.join(','),
            category_id: document.querySelector('[wire\\:model\\.live="category_id"]')?.value || '',
            item_id:     document.querySelector('[wire\\:model\\.live="item_id"]')?.value     || '',
            variant_id:  document.querySelector('[wire\\:model\\.live="variant_id"]')?.value  || '',
            format:      'pdf',
        });
        window.open('/uniform-stock-flow/report?' + params.toString(), '_blank');
        closeModal();
    });

})();
</script>
@endpush
</x-filament-panels::page>