<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use BackedEnum;

class OfficeSupplyStockFlow extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'fas-chart-column';
    protected static ?string $navigationLabel = 'Office Supply Stock Flow';

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    protected static ?int $navigationSort = 7;
    protected string $view = 'filament.pages.office-supply-stock-flow';

    // ── Main dashboard filters (KPIs + Chart) ────────────────────────────────
    public ?string $category_id = null;
    public ?string $item_id     = null;
    public ?string $variant_id  = null;
    public ?string $date_from   = null;
    public ?string $date_to     = null;

    // ── Request Summary independent filters ───────────────────────────────────
    public ?string $summary_category_id = null;
    public ?string $summary_item_id     = null;
    public ?string $summary_variant_id  = null;
    public ?string $summary_date_from   = null;
    public ?string $summary_date_to     = null;

    public string $summary_tab = 'item';

    public function mount(): void
    {
        $this->date_from         = now()->startOfYear()->toDateString();
        $this->date_to           = now()->toDateString();
        $this->summary_date_from = $this->date_from;
        $this->summary_date_to   = $this->date_to;
    }

    // ── Dispatch chart data to JS on every main filter change ─────────────────

    private function dispatchChartData(): void
    {
        $this->dispatch('os-chart-update', ...$this->getFlowChartData());
    }

    public function updatedCategoryId(): void { $this->dispatchChartData(); }
    public function updatedItemId(): void      { $this->dispatchChartData(); }
    public function updatedVariantId(): void   { $this->dispatchChartData(); }
    public function updatedDateFrom(): void    { $this->dispatchChartData(); }
    public function updatedDateTo(): void      { $this->dispatchChartData(); }

    // ── Summary filter watchers (cascade: clear child when parent changes) ────

    public function updatedSummaryCategoryId(): void
    {
        $this->summary_item_id    = null;
        $this->summary_variant_id = null;
    }

    public function updatedSummaryItemId(): void
    {
        $this->summary_variant_id = null;
    }

    // ── Base request (stock out) query — uses MAIN filters ────────────────────

    private function baseRequestQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $from = $this->date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->date_to   ?? now()->toDateString();

        $q = \App\Models\OfficeSupplyRequestItem::query()
            ->join('office_supply_requests', 'office_supply_requests.id', '=', 'office_supply_request_items.office_supply_request_id')
            ->join('office_supply_items', 'office_supply_items.id', '=', 'office_supply_request_items.item_id')
            ->join('office_supply_categories', 'office_supply_categories.id', '=', 'office_supply_items.office_supply_category_id')
            ->leftJoin('office_supply_item_variants', 'office_supply_item_variants.id', '=', 'office_supply_request_items.item_variant_id')
            ->whereIn('office_supply_requests.status', ['completed'])
            ->whereBetween('office_supply_requests.updated_at', [$from, $to . ' 23:59:59']);

        if ($this->item_id)     $q->where('office_supply_request_items.item_id', $this->item_id);
        if ($this->variant_id)  $q->where('office_supply_request_items.item_variant_id', $this->variant_id);
        if ($this->category_id) $q->where('office_supply_items.office_supply_category_id', $this->category_id);

        return $q;
    }

    // ── Summary request query — uses INDEPENDENT summary filters ──────────────

    private function summaryRequestQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $from = $this->summary_date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->summary_date_to   ?? now()->toDateString();

        $q = \App\Models\OfficeSupplyRequestItem::query()
            ->join('office_supply_requests', 'office_supply_requests.id', '=', 'office_supply_request_items.office_supply_request_id')
            ->join('office_supply_items', 'office_supply_items.id', '=', 'office_supply_request_items.item_id')
            ->join('office_supply_categories', 'office_supply_categories.id', '=', 'office_supply_items.office_supply_category_id')
            ->leftJoin('office_supply_item_variants', 'office_supply_item_variants.id', '=', 'office_supply_request_items.item_variant_id')
            ->whereIn('office_supply_requests.status', ['completed'])
            ->whereBetween('office_supply_requests.updated_at', [$from, $to . ' 23:59:59']);

        if ($this->summary_category_id) $q->where('office_supply_items.office_supply_category_id', $this->summary_category_id);
        if ($this->summary_item_id)     $q->where('office_supply_request_items.item_id', $this->summary_item_id);
        if ($this->summary_variant_id)  $q->where('office_supply_request_items.item_variant_id', $this->summary_variant_id);

        return $q;
    }

    // ── Chart data ───────────────────────────────────────────────────────────

    public function getFlowChartData(): array
    {
        $from = $this->date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->date_to   ?? now()->toDateString();

        // Stock IN: Restocks (delivered/partial)
        $restockQuery = \App\Models\OfficeSupplyRestockItem::query()
            ->join('office_supply_restocks', 'office_supply_restocks.id', '=', 'office_supply_restock_items.office_supply_restock_id')
            ->whereIn('office_supply_restocks.status', ['delivered', 'partial'])
            ->whereBetween('office_supply_restocks.delivered_at', [$from, $to])
            ->select('office_supply_restock_items.*', 'office_supply_restocks.delivered_at as restock_delivered_at');

        if ($this->item_id)     $restockQuery->where('office_supply_restock_items.office_supply_item_id', $this->item_id);
        if ($this->variant_id)  $restockQuery->where('office_supply_restock_items.office_supply_item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $restockQuery->join('office_supply_items as ri_items', 'ri_items.id', '=', 'office_supply_restock_items.office_supply_item_id')
                         ->where('ri_items.office_supply_category_id', $this->category_id);
        }

        $restocks = $restockQuery->get()->groupBy(fn ($r) =>
            \Carbon\Carbon::parse($r->restock_delivered_at)->format('Y-m')
        );

        // Stock OUT: Completed Requests
        $reqQuery = \App\Models\OfficeSupplyRequestItem::query()
            ->join('office_supply_requests', 'office_supply_requests.id', '=', 'office_supply_request_items.office_supply_request_id')
            ->whereIn('office_supply_requests.status', ['completed'])
            ->whereBetween('office_supply_requests.updated_at', [$from, $to . ' 23:59:59'])
            ->select('office_supply_request_items.*', 'office_supply_requests.updated_at as completed_at');

        if ($this->item_id)     $reqQuery->where('office_supply_request_items.item_id', $this->item_id);
        if ($this->variant_id)  $reqQuery->where('office_supply_request_items.item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $reqQuery->join('office_supply_items as req_items', 'req_items.id', '=', 'office_supply_request_items.item_id')
                     ->where('req_items.office_supply_category_id', $this->category_id);
        }

        $requests = $reqQuery->get()->groupBy(fn ($r) =>
            \Carbon\Carbon::parse($r->completed_at)->format('Y-m')
        );

        // Stock IN: Returns (add_to_stock = true)
        $returnQuery = \App\Models\ReturnOfficeSupplyItemLine::query()
            ->join('return_office_supply_items', 'return_office_supply_items.id', '=', 'return_office_supply_item_lines.return_office_supply_item_id')
            ->where('return_office_supply_item_lines.add_to_stock', true)
            ->where('return_office_supply_items.status', 'returned')
            ->whereBetween('return_office_supply_items.returned_at', [$from, $to])
            ->select('return_office_supply_item_lines.*', 'return_office_supply_items.returned_at as item_returned_at');

        if ($this->item_id)     $returnQuery->where('return_office_supply_item_lines.office_supply_item_id', $this->item_id);
        if ($this->variant_id)  $returnQuery->where('return_office_supply_item_lines.office_supply_item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $returnQuery->join('office_supply_items as ret_items', 'ret_items.id', '=', 'return_office_supply_item_lines.office_supply_item_id')
                        ->where('ret_items.office_supply_category_id', $this->category_id);
        }

        $returns = $returnQuery->get()->groupBy(fn ($r) =>
            \Carbon\Carbon::parse($r->item_returned_at)->format('Y-m')
        );

        $start   = \Carbon\Carbon::parse($from)->startOfMonth();
        $end     = \Carbon\Carbon::parse($to)->startOfMonth();
        $labels  = [];
        $inData  = [];
        $outData = [];
        $netData = [];

        while ($start->lte($end)) {
            $key    = $start->format('Y-m');
            $label  = $start->format('M Y');
            $inQty  = ($restocks[$key] ?? collect())->sum('delivered_quantity')
                    + ($returns[$key]  ?? collect())->sum('returned_quantity');
            $outQty = ($requests[$key] ?? collect())->sum('quantity');

            $labels[]  = $label;
            $inData[]  = $inQty;
            $outData[] = $outQty;
            $netData[] = $inQty - $outQty;

            $start->addMonth();
        }

        return compact('labels', 'inData', 'outData', 'netData');
    }

    public function getMetrics(): array
    {
        $chart    = $this->getFlowChartData();
        $totalIn  = array_sum($chart['inData']);
        $totalOut = array_sum($chart['outData']);
        $net      = $totalIn - $totalOut;

        $stockQuery = \App\Models\OfficeSupplyItemVariant::query();
        if ($this->item_id)     $stockQuery->where('office_supply_item_id', $this->item_id);
        if ($this->variant_id)  $stockQuery->where('id', $this->variant_id);
        if ($this->category_id) {
            $stockQuery->join('office_supply_items as s_items', 's_items.id', '=', 'office_supply_item_variants.office_supply_item_id')
                       ->where('s_items.office_supply_category_id', $this->category_id);
        }
        $currentStock = $stockQuery->sum('office_supply_quantity');

        return [
            'total_in'      => $totalIn,
            'total_out'     => $totalOut,
            'net'           => $net,
            'current_stock' => $currentStock,
        ];
    }

    // ── Request Summary — uses INDEPENDENT summary filters ────────────────────

    public function getRequestSummary(): array
    {
        $rows = $this->summaryRequestQuery()
            ->select(
                'office_supply_request_items.quantity as released_quantity',
                'office_supply_items.office_supply_name as item_name',
                'office_supply_categories.office_supply_category_name as category_name',
                'office_supply_item_variants.office_supply_variant as variant_name',
                'office_supply_requests.request_number as request_number',
                'office_supply_requests.requested_by as requested_by',
                'office_supply_requests.status as request_status',
            )
            ->get();

        if ($this->summary_tab === 'item') {
            return $rows->groupBy('item_name')
                ->map(fn ($group, $name) => [
                    'label'    => $name,
                    'category' => $group->first()->category_name ?? '—',
                    'total'    => $group->sum('released_quantity'),
                    'breakdown' => $group->groupBy('request_status')
                        ->map(fn ($tg, $type) => [
                            'subtotal' => $tg->sum('released_quantity'),
                            'variants' => $tg->groupBy('variant_name')
                                ->map(fn ($vg) => $vg->sum('released_quantity'))
                                ->sortByDesc(fn ($v) => $v)
                                ->toArray(),
                        ])
                        ->sortByDesc('subtotal')
                        ->toArray(),
                ])
                ->sortByDesc('total')
                ->values()
                ->toArray();
        }

        if ($this->summary_tab === 'requester') {
            return $rows->groupBy('requested_by')
                ->map(fn ($group, $requester) => [
                    'label' => $requester ?? 'Unknown',
                    'total' => $group->sum('released_quantity'),
                    'breakdown' => $group->groupBy('request_status')
                        ->map(fn ($tg, $type) => [
                            'subtotal' => $tg->sum('released_quantity'),
                            'items'    => $tg->groupBy('item_name')
                                ->map(fn ($ig) => $ig->sum('released_quantity'))
                                ->sortByDesc(fn ($v) => $v)
                                ->toArray(),
                        ])
                        ->sortByDesc('subtotal')
                        ->toArray(),
                ])
                ->sortByDesc('total')
                ->values()
                ->toArray();
        }

        // By Request Number
        return $rows->groupBy('request_number')
            ->map(fn ($group, $req) => [
                'label'      => trim($req) ?: 'Unknown',
                'requester'  => $group->first()->requested_by ?? '—',
                'total'      => $group->sum('released_quantity'),
                'breakdown'  => $group->groupBy('request_status')
                    ->map(fn ($tg, $type) => [
                        'subtotal' => $tg->sum('released_quantity'),
                        'items'    => $tg->groupBy('item_name')
                            ->map(fn ($ig) => $ig->sum('released_quantity'))
                            ->sortByDesc(fn ($v) => $v)
                            ->toArray(),
                    ])
                    ->sortByDesc('subtotal')
                    ->toArray(),
            ])
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }

    // ── Report helpers ────────────────────────────────────────────────────────

    public function getStockSummary(): array
    {
        $q = \App\Models\OfficeSupplyItemVariant::query()
            ->join('office_supply_items', 'office_supply_items.id', '=', 'office_supply_item_variants.office_supply_item_id')
            ->join('office_supply_categories', 'office_supply_categories.id', '=', 'office_supply_items.office_supply_category_id')
            ->select(
                'office_supply_items.office_supply_name as item_name',
                'office_supply_categories.office_supply_category_name as category_name',
                'office_supply_item_variants.office_supply_variant as variant',
                'office_supply_item_variants.office_supply_quantity as quantity',
                'office_supply_item_variants.id as variant_id',
            )
            ->orderBy('office_supply_categories.office_supply_category_name')
            ->orderBy('office_supply_items.office_supply_name')
            ->orderBy('office_supply_item_variants.office_supply_variant');

        if ($this->item_id)     $q->where('office_supply_item_variants.office_supply_item_id', $this->item_id);
        if ($this->variant_id)  $q->where('office_supply_item_variants.id', $this->variant_id);
        if ($this->category_id) $q->where('office_supply_items.office_supply_category_id', $this->category_id);

        return $q->get()->toArray();
    }

    // ── Filter option lists ───────────────────────────────────────────────────

    /** Main dashboard Category filter */
    public function getCategoryOptions(): array
    {
        return \App\Models\OfficeSupplyCategory::orderBy('office_supply_category_name')
            ->pluck('office_supply_category_name', 'id')
            ->toArray();
    }

    /** Main dashboard Item filter */
    public function getItemOptions(): array
    {
        $q = \App\Models\OfficeSupplyItem::orderBy('office_supply_name');
        if ($this->category_id) $q->where('office_supply_category_id', $this->category_id);
        return $q->pluck('office_supply_name', 'id')->toArray();
    }

    /** Main dashboard Variant filter */
    public function getVariantOptions(): array
    {
        $q = \App\Models\OfficeSupplyItemVariant::orderBy('office_supply_variant');
        if ($this->item_id) $q->where('office_supply_item_id', $this->item_id);
        return $q->get()
            ->mapWithKeys(fn ($v) => [$v->id => $v->office_supply_variant])
            ->toArray();
    }

    /** Summary panel Item filter (respects summary_category_id) */
    public function getSummaryItemOptions(): array
    {
        $q = \App\Models\OfficeSupplyItem::orderBy('office_supply_name');
        if ($this->summary_category_id) $q->where('office_supply_category_id', $this->summary_category_id);
        return $q->pluck('office_supply_name', 'id')->toArray();
    }

    /** Summary panel Variant filter (respects summary_item_id) */
    public function getSummaryVariantOptions(): array
    {
        $q = \App\Models\OfficeSupplyItemVariant::orderBy('office_supply_variant');
        if ($this->summary_item_id) $q->where('office_supply_item_id', $this->summary_item_id);
        return $q->get()
            ->mapWithKeys(fn ($v) => [$v->id => $v->office_supply_variant])
            ->toArray();
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function setSummaryTab(string $tab): void
    {
        $this->summary_tab = $tab;
    }

    /** Reset main dashboard filters (KPIs + Chart) */
    public function resetFlowFilters(): void
    {
        $this->category_id = null;
        $this->item_id     = null;
        $this->variant_id  = null;
        $this->date_from   = now()->startOfYear()->toDateString();
        $this->date_to     = now()->toDateString();
        $this->dispatchChartData();
    }

    /** Reset Request Summary independent filters */
    public function resetSummaryFilters(): void
    {
        $this->summary_category_id = null;
        $this->summary_item_id     = null;
        $this->summary_variant_id  = null;
        $this->summary_date_from   = now()->startOfYear()->toDateString();
        $this->summary_date_to     = now()->toDateString();
    }

    /** Reset only the summary date range (used by the × badge) */
    public function resetSummaryDates(): void
    {
        $this->summary_date_from = $this->date_from ?? now()->startOfYear()->toDateString();
        $this->summary_date_to   = $this->date_to   ?? now()->toDateString();
    }
}