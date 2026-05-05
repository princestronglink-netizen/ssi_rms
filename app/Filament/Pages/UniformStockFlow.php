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

    // ── Main dashboard filters (KPIs + Chart) ────────────────────────────────
    public ?string $category_id = null;
    public ?string $item_id     = null;
    public ?string $variant_id  = null;
    public ?string $date_from   = null;
    public ?string $date_to     = null;

    // ── Issuance Summary independent filters ─────────────────────────────────
    public ?string $summary_category_id = null;
    public ?string $summary_item_id     = null;
    public ?string $summary_variant_id  = null;
    public ?string $summary_date_from   = null;
    public ?string $summary_date_to     = null;

    public string $summary_tab = 'item';

    // ── Report generation state ───────────────────────────────────────────────
    public bool $report_generating = false;

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
        $this->dispatch('sf-chart-update', ...$this->getFlowChartData());
    }

    public function updatedCategoryId(): void { $this->dispatchChartData(); }
    public function updatedItemId(): void      { $this->dispatchChartData(); }
    public function updatedVariantId(): void   { $this->dispatchChartData(); }
    public function updatedDateFrom(): void    { $this->dispatchChartData(); }
    public function updatedDateTo(): void      { $this->dispatchChartData(); }

    // ── Summary filter watchers (cascade: clear child when parent changes) ────

    public function updatedSummaryCategoryId(): void
    {
        // If category changes, reset item & variant so stale values don't filter
        $this->summary_item_id    = null;
        $this->summary_variant_id = null;
    }

    public function updatedSummaryItemId(): void
    {
        // If item changes, reset variant
        $this->summary_variant_id = null;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Base query for the main KPI / chart issuance data.
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
            ->leftJoin('uniform_issuance_types', 'uniform_issuance_types.id', '=', 'uniform_issuances.uniform_issuance_type_id')
            ->whereIn('uniform_issuances.uniform_issuance_status', ['issued', 'partial'])
            ->whereBetween('uniform_issuances.issued_at', [$from, $to]);
    }

    /**
     * Base query for the Issuance Summary section — uses its own independent filters.
     */
    private function summaryIssuanceQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $from = $this->summary_date_from ?? now()->startOfYear()->toDateString();
        $to   = $this->summary_date_to   ?? now()->toDateString();

        $q = \App\Models\UniformIssuanceItems::query()
            ->join('uniform_issuance_recipients', 'uniform_issuance_recipients.id', '=', 'uniform_issuance_items.uniform_issuance_recipient_id')
            ->join('uniform_issuances', 'uniform_issuances.id', '=', 'uniform_issuance_recipients.uniform_issuance_id')
            ->join('uniform_items', 'uniform_items.id', '=', 'uniform_issuance_items.uniform_item_id')
            ->join('uniform_categories', 'uniform_categories.id', '=', 'uniform_items.uniform_category_id')
            ->leftJoin('uniform_item_variants', 'uniform_item_variants.id', '=', 'uniform_issuance_items.uniform_item_variant_id')
            ->leftJoin('sites', 'sites.id', '=', 'uniform_issuances.site_id')
            ->leftJoin('uniform_issuance_types', 'uniform_issuance_types.id', '=', 'uniform_issuances.uniform_issuance_type_id')
            ->whereIn('uniform_issuances.uniform_issuance_status', ['issued', 'partial'])
            ->whereBetween('uniform_issuances.issued_at', [$from, $to]);

        if ($this->summary_category_id) {
            $q->where('uniform_items.uniform_category_id', $this->summary_category_id);
        }
        if ($this->summary_item_id) {
            $q->where('uniform_issuance_items.uniform_item_id', $this->summary_item_id);
        }
        if ($this->summary_variant_id) {
            $q->where('uniform_issuance_items.uniform_item_variant_id', $this->summary_variant_id);
        }

        return $q;
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

    // ── Issuance Summary — uses independent summary filters ──────────────────

    public function getIssuanceSummary(): array
    {
        $rows = $this->summaryIssuanceQuery()
            ->select(
                'uniform_issuance_items.released_quantity',
                'uniform_items.uniform_item_name as item_name',
                'uniform_categories.uniform_category_name as category_name',
                'uniform_item_variants.uniform_item_size as variant_size',
                'sites.site_name as site_name',
                'uniform_issuance_recipients.employee_name as person_name',
                'uniform_issuance_types.uniform_issuance_type_name as issuance_type',
            )
            ->get();

        if ($this->summary_tab === 'item') {
            return $rows->groupBy('item_name')
                ->map(fn ($group, $name) => [
                    'label'          => $name,
                    'category'       => $group->first()->category_name ?? '—',
                    'total'          => $group->sum('released_quantity'),
                    'issuance_types' => $group->groupBy('issuance_type')
                        ->map(fn ($tg) => [
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
                    'label'          => $site ?? 'Unknown',
                    'total'          => $group->sum('released_quantity'),
                    'issuance_types' => $group->groupBy('issuance_type')
                        ->map(fn ($tg) => [
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
                'label'          => trim($person) ?: 'Unknown',
                'site'           => $group->first()->site_name ?? '—',
                'total'          => $group->sum('released_quantity'),
                'issuance_types' => $group->groupBy('issuance_type')
                    ->map(fn ($tg) => [
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

    // ── Report Generation ────────────────────────────────────────────────────

    public function generateReport(string $date_from, string $date_to, array $sections = [], string $format = 'pdf'): void
    {
        $originalFrom = $this->date_from;
        $originalTo   = $this->date_to;

        $this->date_from = $date_from;
        $this->date_to   = $date_to;

        $payload = [
            'date_from'   => $date_from,
            'date_to'     => $date_to,
            'sections'    => $sections,
            'format'      => $format,
            'category_id' => $this->category_id,
            'item_id'     => $this->item_id,
            'variant_id'  => $this->variant_id,
            'metrics'     => $this->getMetrics(),
            'chart_data'  => $this->getFlowChartData(),
        ];

        if (in_array('issuances', $sections) || in_array('issuance_by_site', $sections) || in_array('issuance_by_person', $sections)) {
            $savedTab = $this->summary_tab;

            if (in_array('issuances', $sections)) {
                $this->summary_tab = 'item';
                $payload['issuance_by_item'] = $this->getIssuanceSummary();
            }
            if (in_array('issuance_by_site', $sections)) {
                $this->summary_tab = 'site';
                $payload['issuance_by_site'] = $this->getIssuanceSummary();
            }
            if (in_array('issuance_by_person', $sections)) {
                $this->summary_tab = 'person';
                $payload['issuance_by_person'] = $this->getIssuanceSummary();
            }

            $this->summary_tab = $savedTab;
        }

        if (in_array('stock_summary', $sections)) {
            $payload['stock_summary'] = $this->getStockSummary();
        }
        if (in_array('restocks', $sections)) {
            $payload['restocks'] = $this->getRestockSummary($date_from, $date_to);
        }
        if (in_array('returns', $sections)) {
            $payload['returns'] = $this->getReturnSummary($date_from, $date_to);
        }

        $this->date_from = $originalFrom;
        $this->date_to   = $originalTo;

        $this->dispatch('notify', [
            'title'  => 'Report Queued',
            'body'   => "Your {$format} report ({$date_from} → {$date_to}) is being prepared.",
            'status' => 'success',
        ]);
    }

    // ── Stock / Restock / Return summaries for report ────────────────────────

    public function getStockSummary(): array
    {
        $q = \App\Models\UniformItemVariants::query()
            ->join('uniform_items', 'uniform_items.id', '=', 'uniform_item_variants.uniform_item_id')
            ->join('uniform_categories', 'uniform_categories.id', '=', 'uniform_items.uniform_category_id')
            ->select(
                'uniform_items.uniform_item_name as item_name',
                'uniform_categories.uniform_category_name as category_name',
                'uniform_item_variants.uniform_item_size as size',
                'uniform_item_variants.uniform_item_quantity as quantity',
                'uniform_item_variants.id as variant_id',
            )
            ->orderBy('uniform_categories.uniform_category_name')
            ->orderBy('uniform_items.uniform_item_name')
            ->orderBy('uniform_item_variants.uniform_item_size');

        if ($this->item_id)     $q->where('uniform_item_variants.uniform_item_id', $this->item_id);
        if ($this->variant_id)  $q->where('uniform_item_variants.id', $this->variant_id);
        if ($this->category_id) $q->where('uniform_items.uniform_category_id', $this->category_id);

        return $q->get()->toArray();
    }

    private function getRestockSummary(string $from, string $to): array
    {
        $q = \App\Models\UniformRestockItems::query()
            ->join('uniform_restocks', 'uniform_restocks.id', '=', 'uniform_restock_items.uniform_restock_id')
            ->join('uniform_items', 'uniform_items.id', '=', 'uniform_restock_items.uniform_item_id')
            ->join('uniform_categories', 'uniform_categories.id', '=', 'uniform_items.uniform_category_id')
            ->leftJoin('uniform_item_variants', 'uniform_item_variants.id', '=', 'uniform_restock_items.uniform_item_variant_id')
            ->whereIn('uniform_restocks.status', ['delivered', 'partial'])
            ->whereBetween('uniform_restocks.delivered_at', [$from, $to])
            ->select(
                'uniform_items.uniform_item_name as item_name',
                'uniform_categories.uniform_category_name as category_name',
                'uniform_item_variants.uniform_item_size as size',
                'uniform_restock_items.delivered_quantity',
                'uniform_restocks.delivered_at',
            )
            ->orderBy('uniform_restocks.delivered_at');

        if ($this->item_id)     $q->where('uniform_restock_items.uniform_item_id', $this->item_id);
        if ($this->variant_id)  $q->where('uniform_restock_items.uniform_item_variant_id', $this->variant_id);
        if ($this->category_id) $q->where('uniform_items.uniform_category_id', $this->category_id);

        return $q->get()->toArray();
    }

    private function getReturnSummary(string $from, string $to): array
    {
        $q = \App\Models\ReturnUniformItemLine::query()
            ->join('return_uniform_items', 'return_uniform_items.id', '=', 'return_uniform_item_lines.return_uniform_item_id')
            ->join('uniform_items', 'uniform_items.id', '=', 'return_uniform_item_lines.uniform_item_id')
            ->join('uniform_categories', 'uniform_categories.id', '=', 'uniform_items.uniform_category_id')
            ->leftJoin('uniform_item_variants', 'uniform_item_variants.id', '=', 'return_uniform_item_lines.uniform_item_variant_id')
            ->where('return_uniform_item_lines.add_to_stock', true)
            ->where('return_uniform_items.status', 'returned')
            ->whereBetween('return_uniform_items.returned_at', [$from, $to])
            ->select(
                'uniform_items.uniform_item_name as item_name',
                'uniform_categories.uniform_category_name as category_name',
                'uniform_item_variants.uniform_item_size as size',
                'return_uniform_item_lines.returned_quantity',
                'return_uniform_items.returned_at',
            )
            ->orderBy('return_uniform_items.returned_at');

        if ($this->item_id)     $q->where('return_uniform_item_lines.uniform_item_id', $this->item_id);
        if ($this->variant_id)  $q->where('return_uniform_item_lines.uniform_item_variant_id', $this->variant_id);
        if ($this->category_id) $q->where('uniform_items.uniform_category_id', $this->category_id);

        return $q->get()->toArray();
    }

    // ── Filter option lists ───────────────────────────────────────────────────

    /** Options for the main dashboard Category filter */
    public function getCategoryOptions(): array
    {
        return \App\Models\UniformCategory::orderBy('uniform_category_name')
            ->pluck('uniform_category_name', 'id')
            ->toArray();
    }

    /** Options for the main dashboard Item filter */
    public function getItemOptions(): array
    {
        $q = \App\Models\UniformItems::orderBy('uniform_item_name');
        if ($this->category_id) $q->where('uniform_category_id', $this->category_id);
        return $q->pluck('uniform_item_name', 'id')->toArray();
    }

    /** Options for the main dashboard Variant filter */
    public function getVariantOptions(): array
    {
        $q = \App\Models\UniformItemVariants::orderBy('uniform_item_size');
        if ($this->item_id) $q->where('uniform_item_id', $this->item_id);
        return $q->get()->mapWithKeys(fn ($v) => [$v->id => $v->uniform_item_size])->toArray();
    }

    /** Options for the Summary panel Item filter (respects summary_category_id) */
    public function getSummaryItemOptions(): array
    {
        $q = \App\Models\UniformItems::orderBy('uniform_item_name');
        if ($this->summary_category_id) $q->where('uniform_category_id', $this->summary_category_id);
        return $q->pluck('uniform_item_name', 'id')->toArray();
    }

    /** Options for the Summary panel Variant filter (respects summary_item_id) */
    public function getSummaryVariantOptions(): array
    {
        $q = \App\Models\UniformItemVariants::orderBy('uniform_item_size');
        if ($this->summary_item_id) $q->where('uniform_item_id', $this->summary_item_id);
        return $q->get()->mapWithKeys(fn ($v) => [$v->id => $v->uniform_item_size])->toArray();
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function setSummaryTab(string $tab): void
    {
        $this->summary_tab = $tab;
    }

    /** Reset main dashboard filters */
    public function resetFlowFilters(): void
    {
        $this->category_id = null;
        $this->item_id     = null;
        $this->variant_id  = null;
        $this->date_from   = now()->startOfYear()->toDateString();
        $this->date_to     = now()->toDateString();
        $this->dispatchChartData();
    }

    /** Reset Issuance Summary filters */
    public function resetSummaryFilters(): void
    {
        $this->summary_category_id = null;
        $this->summary_item_id     = null;
        $this->summary_variant_id  = null;
        $this->summary_date_from   = now()->startOfYear()->toDateString();
        $this->summary_date_to     = now()->toDateString();
    }

    /** Reset only the date range on the Issuance Summary (used by the × badge) */
    public function resetSummaryDates(): void
    {
        $this->summary_date_from = $this->date_from ?? now()->startOfYear()->toDateString();
        $this->summary_date_to   = $this->date_to   ?? now()->toDateString();
    }
}