<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use BackedEnum;

class UniformStockFlow extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'fas-chart-column';
    protected static ?string $navigationLabel = 'Uniform Stock Flow';

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.pages.uniform-stock-flow';

    public ?string $category_id = null;
    public ?string $item_id     = null;
    public ?string $variant_id  = null;
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

    public function updatedCategoryId(): void  { $this->dispatchChartData(); }
    public function updatedItemId(): void       { $this->dispatchChartData(); }
    public function updatedVariantId(): void    { $this->dispatchChartData(); }
    public function updatedDateFrom(): void     { $this->dispatchChartData(); }
    public function updatedDateTo(): void       { $this->dispatchChartData(); }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Base query for issuance items — always applies date filter + status filter.
     * Includes issuance_type from uniform_issuances.
     */
    private function baseIssuanceQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $from = $this->date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->date_to   ?? now()->toDateString();

        return \App\Models\UniformIssuanceItems::query()
            ->join('uniform_issuance_recipients', 'uniform_issuance_recipients.id', '=', 'uniform_issuance_items.uniform_issuance_recipient_id')
            ->join('uniform_issuances', 'uniform_issuances.id', '=', 'uniform_issuance_recipients.uniform_issuance_id')
            ->join('uniform_items', 'uniform_items.id', '=', 'uniform_issuance_items.uniform_item_id')
            ->join('uniform_categories', 'uniform_categories.id', '=', 'uniform_items.uniform_category_id')
            ->leftJoin('uniform_item_variants', 'uniform_item_variants.id', '=', 'uniform_issuance_items.uniform_item_variant_id')
            ->leftJoin('sites', 'sites.id', '=', 'uniform_issuances.site_id')
            // ✅ Join the issuance type lookup table — type is stored as FK, not a plain string
            ->leftJoin('uniform_issuance_types', 'uniform_issuance_types.id', '=', 'uniform_issuances.uniform_issuance_type_id')
            ->whereIn('uniform_issuances.uniform_issuance_status', ['issued', 'partial'])
            ->whereBetween('uniform_issuances.issued_at', [$from, $to]);
    }

    // ── Chart data ───────────────────────────────────────────────────────────

    public function getFlowChartData(): array
    {
        $from = $this->date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->date_to   ?? now()->toDateString();

        $restockQuery = \App\Models\UniformRestockItems::query()
            ->join('uniform_restocks', 'uniform_restocks.id', '=', 'uniform_restock_items.uniform_restock_id')
            ->whereIn('uniform_restocks.status', ['delivered', 'partial'])
            ->whereBetween('uniform_restocks.delivered_at', [$from, $to])
            ->select('uniform_restock_items.*', 'uniform_restocks.delivered_at as restock_delivered_at');

        if ($this->item_id)     $restockQuery->where('uniform_restock_items.uniform_item_id', $this->item_id);
        if ($this->variant_id)  $restockQuery->where('uniform_restock_items.uniform_item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $restockQuery->join('uniform_items as ri_items', 'ri_items.id', '=', 'uniform_restock_items.uniform_item_id')
                         ->where('ri_items.uniform_category_id', $this->category_id);
        }

        $restocks = $restockQuery->get()->groupBy(fn ($r) =>
            \Carbon\Carbon::parse($r->restock_delivered_at)->format('Y-m')
        );

        $issuanceQuery = \App\Models\UniformIssuanceItems::query()
            ->join('uniform_issuance_recipients', 'uniform_issuance_recipients.id', '=', 'uniform_issuance_items.uniform_issuance_recipient_id')
            ->join('uniform_issuances', 'uniform_issuances.id', '=', 'uniform_issuance_recipients.uniform_issuance_id')
            ->whereIn('uniform_issuances.uniform_issuance_status', ['issued', 'partial'])
            ->whereBetween('uniform_issuances.issued_at', [$from, $to])
            ->select('uniform_issuance_items.*', 'uniform_issuances.issued_at as issuance_issued_at');

        if ($this->item_id)     $issuanceQuery->where('uniform_issuance_items.uniform_item_id', $this->item_id);
        if ($this->variant_id)  $issuanceQuery->where('uniform_issuance_items.uniform_item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $issuanceQuery->join('uniform_items as ii_items', 'ii_items.id', '=', 'uniform_issuance_items.uniform_item_id')
                          ->where('ii_items.uniform_category_id', $this->category_id);
        }

        $issuances = $issuanceQuery->get()->groupBy(fn ($r) =>
            \Carbon\Carbon::parse($r->issuance_issued_at)->format('Y-m')
        );

        $returnQuery = \App\Models\ReturnUniformItemLine::query()
            ->join('return_uniform_items', 'return_uniform_items.id', '=', 'return_uniform_item_lines.return_uniform_item_id')
            ->where('return_uniform_item_lines.add_to_stock', true)
            ->where('return_uniform_items.status', 'returned')
            ->whereBetween('return_uniform_items.returned_at', [$from, $to])
            ->select('return_uniform_item_lines.*', 'return_uniform_items.returned_at as item_returned_at');

        if ($this->item_id)     $returnQuery->where('return_uniform_item_lines.uniform_item_id', $this->item_id);
        if ($this->variant_id)  $returnQuery->where('return_uniform_item_lines.uniform_item_variant_id', $this->variant_id);
        if ($this->category_id) {
            $returnQuery->join('uniform_items as ret_items', 'ret_items.id', '=', 'return_uniform_item_lines.uniform_item_id')
                        ->where('ret_items.uniform_category_id', $this->category_id);
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
            $outQty = ($issuances[$key] ?? collect())->sum('released_quantity');

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

        $stockQuery = \App\Models\UniformItemVariants::query();
        if ($this->item_id)     $stockQuery->where('uniform_item_id', $this->item_id);
        if ($this->variant_id)  $stockQuery->where('id', $this->variant_id);
        if ($this->category_id) {
            $stockQuery->join('uniform_items as s_items', 's_items.id', '=', 'uniform_item_variants.uniform_item_id')
                       ->where('s_items.uniform_category_id', $this->category_id);
        }
        $currentStock = $stockQuery->sum('uniform_item_quantity');

        return [
            'total_in'      => $totalIn,
            'total_out'     => $totalOut,
            'net'           => $net,
            'current_stock' => $currentStock,
        ];
    }

    // ── Issuance Summary — includes issuance_type ─────────────────────────────

    public function getIssuanceSummary(): array
    {
        $rows = $this->baseIssuanceQuery()
            ->select(
                'uniform_issuance_items.released_quantity',
                'uniform_items.uniform_item_name as item_name',
                'uniform_categories.uniform_category_name as category_name',
                'uniform_item_variants.uniform_item_size as variant_size',
                'sites.site_name as site_name',
                'uniform_issuance_recipients.employee_name as person_name',
                // ✅ Pull type name from the joined uniform_issuance_types table
                'uniform_issuance_types.uniform_issuance_type_name as issuance_type',
            )
            ->get();

        if ($this->summary_tab === 'item') {
            return $rows->groupBy('item_name')
                ->map(fn ($group, $name) => [
                    'label'    => $name,
                    'category' => $group->first()->category_name ?? '—',
                    'total'    => $group->sum('released_quantity'),
                    // ✅ Each issuance type → its sizes and subtotal
                    'issuance_types' => $group->groupBy('issuance_type')
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
                    // ✅ Each issuance type → its items and subtotal
                    'issuance_types' => $group->groupBy('issuance_type')
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

        // By person
        return $rows->groupBy('person_name')
            ->map(fn ($group, $person) => [
                'label' => trim($person) ?: 'Unknown',
                'site'  => $group->first()->site_name ?? '—',
                'total' => $group->sum('released_quantity'),
                // ✅ Each issuance type → its items and subtotal
                'issuance_types' => $group->groupBy('issuance_type')
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

    // ── Filter option lists ───────────────────────────────────────────────────

    public function getCategoryOptions(): array
    {
        return \App\Models\UniformCategory::orderBy('uniform_category_name')
            ->pluck('uniform_category_name', 'id')
            ->toArray();
    }

    public function getItemOptions(): array
    {
        $q = \App\Models\UniformItems::orderBy('uniform_item_name');
        if ($this->category_id) $q->where('uniform_category_id', $this->category_id);
        return $q->pluck('uniform_item_name', 'id')->toArray();
    }

    public function getVariantOptions(): array
    {
        $q = \App\Models\UniformItemVariants::orderBy('uniform_item_size');
        if ($this->item_id) $q->where('uniform_item_id', $this->item_id);
        return $q->get()
            ->mapWithKeys(fn ($v) => [$v->id => $v->uniform_item_size])
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
        $this->date_from   = now()->startOfYear()->toDateString();
        $this->date_to     = now()->toDateString();
        $this->dispatchChartData();
    }
}