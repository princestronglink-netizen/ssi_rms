<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use BackedEnum;

class SmeStockFlow extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'fas-chart-column';
    protected static ?string $navigationLabel = 'SME Stock Flow';

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.pages.sme-stock-flow';

    public ?string $category_id = null;
    public ?string $item_id     = null;
    public ?string $variant_id  = null;
    public ?string $site_id     = null;
    public ?string $date_from   = null;
    public ?string $date_to     = null;

    public string $summary_tab = 'item';

    public function mount(): void
    {
        $this->date_from = now()->startOfYear()->toDateString();
        $this->date_to   = now()->toDateString();
    }

    // ── Dispatch chart data to JS on every filter change ─────────────────────

    private function dispatchChartData(): void
    {
        $this->dispatch('sf-chart-update', ...$this->getFlowChartData());
    }

    public function updatedCategoryId(): void { $this->dispatchChartData(); }
    public function updatedItemId(): void      { $this->dispatchChartData(); }
    public function updatedVariantId(): void   { $this->dispatchChartData(); }
    public function updatedSiteId(): void      { $this->dispatchChartData(); }
    public function updatedDateFrom(): void    { $this->dispatchChartData(); }
    public function updatedDateTo(): void      { $this->dispatchChartData(); }

    // ── Base purchase-order (stock out) query ─────────────────────────────────

    private function basePurchaseOrderQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $from = $this->date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->date_to   ?? now()->toDateString();

        $q = \App\Models\SmePurchaseOrderItems::query()
            ->join('sme_purchase_orders', 'sme_purchase_orders.id', '=', 'sme_purchase_order_items.sme_purchase_order_id')
            ->join('sme_items', 'sme_items.id', '=', 'sme_purchase_order_items.sme_item_id')
            ->join('sme_categories', 'sme_categories.id', '=', 'sme_items.sme_category_id')
            ->leftJoin('sme_item_variants', 'sme_item_variants.id', '=', 'sme_purchase_order_items.sme_item_variant_id')
            ->leftJoin('sites', 'sites.id', '=', 'sme_purchase_orders.site_id')
            ->whereIn('sme_purchase_orders.status', ['approved'])
            ->whereBetween('sme_purchase_orders.approved_at', [$from, $to]);

        if ($this->item_id)     $q->where('sme_purchase_order_items.sme_item_id', $this->item_id);
        if ($this->variant_id)  $q->where('sme_purchase_order_items.sme_item_variant_id', $this->variant_id);
        if ($this->category_id) $q->where('sme_items.sme_category_id', $this->category_id);
        if ($this->site_id)     $q->where('sme_purchase_orders.site_id', $this->site_id);

        return $q;
    }

    // ── Chart data ───────────────────────────────────────────────────────────

    public function getFlowChartData(): array
    {
        $from = $this->date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->date_to   ?? now()->toDateString();

        // Stock IN: Restocks (delivered/partial)
        $restockQuery = \App\Models\SmeRestockItems::query()
            ->join('sme_restocks', 'sme_restocks.id', '=', 'sme_restock_items.sme_restock_id')
            ->whereIn('sme_restocks.status', ['delivered', 'partial'])
            ->whereBetween('sme_restocks.delivered_at', [$from, $to])
            ->select('sme_restock_items.*', 'sme_restocks.delivered_at as restock_delivered_at');

        if ($this->item_id)     $restockQuery->where('sme_restock_items.sme_item_id', $this->item_id);
        if ($this->variant_id)  $restockQuery->where('sme_restock_items.sme_item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $restockQuery->join('sme_items as ri_items', 'ri_items.id', '=', 'sme_restock_items.sme_item_id')
                         ->where('ri_items.sme_category_id', $this->category_id);
        }

        $restocks = $restockQuery->get()->groupBy(fn ($r) =>
            \Carbon\Carbon::parse($r->restock_delivered_at)->format('Y-m')
        );

        // Stock OUT: Approved Purchase Orders
        $poQuery = \App\Models\SmePurchaseOrderItems::query()
            ->join('sme_purchase_orders', 'sme_purchase_orders.id', '=', 'sme_purchase_order_items.sme_purchase_order_id')
            ->whereIn('sme_purchase_orders.status', ['approved'])
            ->whereBetween('sme_purchase_orders.approved_at', [$from, $to])
            ->select('sme_purchase_order_items.*', 'sme_purchase_orders.approved_at as po_approved_at');

        if ($this->item_id)     $poQuery->where('sme_purchase_order_items.sme_item_id', $this->item_id);
        if ($this->variant_id)  $poQuery->where('sme_purchase_order_items.sme_item_variant_id', $this->variant_id);
        if ($this->site_id)     $poQuery->where('sme_purchase_orders.site_id', $this->site_id);
        if ($this->category_id) {
            $poQuery->join('sme_items as po_items', 'po_items.id', '=', 'sme_purchase_order_items.sme_item_id')
                    ->where('po_items.sme_category_id', $this->category_id);
        }

        $purchaseOrders = $poQuery->get()->groupBy(fn ($r) =>
            \Carbon\Carbon::parse($r->po_approved_at)->format('Y-m')
        );

        // Stock IN: Returns (add_to_stock = true)
        $returnQuery = \App\Models\ReturnSmeItemLine::query()
            ->join('return_sme_items', 'return_sme_items.id', '=', 'return_sme_item_lines.return_sme_item_id')
            ->where('return_sme_item_lines.add_to_stock', true)
            ->where('return_sme_items.status', 'returned')
            ->whereBetween('return_sme_items.returned_at', [$from, $to])
            ->select('return_sme_item_lines.*', 'return_sme_items.returned_at as item_returned_at');

        if ($this->item_id)     $returnQuery->where('return_sme_item_lines.sme_item_id', $this->item_id);
        if ($this->variant_id)  $returnQuery->where('return_sme_item_lines.sme_item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $returnQuery->join('sme_items as ret_items', 'ret_items.id', '=', 'return_sme_item_lines.sme_item_id')
                        ->where('ret_items.sme_category_id', $this->category_id);
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
            $inQty  = ($restocks[$key]       ?? collect())->sum('delivered_quantity')
                    + ($returns[$key]         ?? collect())->sum('returned_quantity');
            $outQty = ($purchaseOrders[$key]  ?? collect())->sum('quantity');

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

        $stockQuery = \App\Models\SmeItemVariants::query();
        if ($this->item_id)     $stockQuery->where('sme_item_id', $this->item_id);
        if ($this->variant_id)  $stockQuery->where('id', $this->variant_id);
        if ($this->category_id) {
            $stockQuery->join('sme_items as s_items', 's_items.id', '=', 'sme_item_variants.sme_item_id')
                       ->where('s_items.sme_category_id', $this->category_id);
        }
        $currentStock = $stockQuery->sum('sme_item_quantity');

        return [
            'total_in'      => $totalIn,
            'total_out'     => $totalOut,
            'net'           => $net,
            'current_stock' => $currentStock,
        ];
    }

    // ── Issuance (PO) Summary ─────────────────────────────────────────────────

    public function getIssuanceSummary(): array
    {
        $rows = $this->basePurchaseOrderQuery()
            ->select(
                'sme_purchase_order_items.quantity as released_quantity',
                'sme_items.sme_item_name as item_name',
                'sme_categories.sme_category_name as category_name',
                'sme_item_variants.sme_item_size as variant_size',
                'sites.site_name as site_name',
                'sme_purchase_orders.po_number as po_number',
                'sme_purchase_orders.status as po_status',
            )
            ->get();

        if ($this->summary_tab === 'item') {
            return $rows->groupBy('item_name')
                ->map(fn ($group, $name) => [
                    'label'    => $name,
                    'category' => $group->first()->category_name ?? '—',
                    'total'    => $group->sum('released_quantity'),
                    'issuance_types' => $group->groupBy('po_status')
                        ->map(fn ($tg, $type) => [
                            'subtotal' => $tg->sum('released_quantity'),
                            'sizes'    => $tg->groupBy('variant_size')
                                ->map(fn ($sg) => $sg->sum('released_quantity'))
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

        if ($this->summary_tab === 'site') {
            return $rows->groupBy('site_name')
                ->map(fn ($group, $site) => [
                    'label' => $site ?? 'Unknown',
                    'total' => $group->sum('released_quantity'),
                    'issuance_types' => $group->groupBy('po_status')
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

        // By PO Number
        return $rows->groupBy('po_number')
            ->map(fn ($group, $po) => [
                'label' => trim($po) ?: 'Unknown',
                'site'  => $group->first()->site_name ?? '—',
                'total' => $group->sum('released_quantity'),
                'issuance_types' => $group->groupBy('po_status')
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

    // ── Report helpers (public so controller can call them) ───────────────────

    public function getStockSummary(): array
    {
        $q = \App\Models\SmeItemVariants::query()
            ->join('sme_items', 'sme_items.id', '=', 'sme_item_variants.sme_item_id')
            ->join('sme_categories', 'sme_categories.id', '=', 'sme_items.sme_category_id')
            ->select(
                'sme_items.sme_item_name as item_name',
                'sme_categories.sme_category_name as category_name',
                'sme_item_variants.sme_item_size as size',
                'sme_item_variants.sme_item_quantity as quantity',
                'sme_item_variants.id as variant_id',
            )
            ->orderBy('sme_categories.sme_category_name')
            ->orderBy('sme_items.sme_item_name')
            ->orderBy('sme_item_variants.sme_item_size');

        if ($this->item_id)     $q->where('sme_item_variants.sme_item_id', $this->item_id);
        if ($this->variant_id)  $q->where('sme_item_variants.id', $this->variant_id);
        if ($this->category_id) $q->where('sme_items.sme_category_id', $this->category_id);

        return $q->get()->toArray();
    }

    // ── Filter option lists ───────────────────────────────────────────────────

    public function getCategoryOptions(): array
    {
        return \App\Models\SmeCategory::orderBy('sme_category_name')
            ->pluck('sme_category_name', 'id')
            ->toArray();
    }

    public function getItemOptions(): array
    {
        $q = \App\Models\SmeItems::orderBy('sme_item_name');
        if ($this->category_id) $q->where('sme_category_id', $this->category_id);
        return $q->pluck('sme_item_name', 'id')->toArray();
    }

    public function getVariantOptions(): array
    {
        $q = \App\Models\SmeItemVariants::orderBy('sme_item_size');
        if ($this->item_id) $q->where('sme_item_id', $this->item_id);
        return $q->get()
            ->mapWithKeys(fn ($v) => [$v->id => $v->sme_item_size])
            ->toArray();
    }

    public function getSiteOptions(): array
    {
        return \App\Models\Sites::orderBy('site_name')
            ->pluck('site_name', 'id')
            ->toArray();
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function setSummaryTab(string $tab): void
    {
        $this->summary_tab = $tab;
    }

    public function resetFlowFilters(): void
    {
        $this->category_id = null;
        $this->item_id     = null;
        $this->variant_id  = null;
        $this->site_id     = null;
        $this->date_from   = now()->startOfYear()->toDateString();
        $this->date_to     = now()->toDateString();
        $this->dispatchChartData();
    }
}