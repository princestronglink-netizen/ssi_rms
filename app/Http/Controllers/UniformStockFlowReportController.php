<?php

namespace App\Http\Controllers;

use App\Filament\Pages\UniformStockFlow;
use App\Services\UniformStockFlowReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UniformStockFlowReportController extends Controller
{
    public function download(Request $request): \Illuminate\Http\Response
    {
        abort_unless(Auth::check(), 403);

        $request->validate([
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
            'sections'  => 'nullable|string',
        ]);

        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');
        $sections   = array_filter(explode(',', $request->input('sections', '')));
        $categoryId = $request->input('category_id') ?: null;
        $itemId     = $request->input('item_id')     ?: null;
        $variantId  = $request->input('variant_id')  ?: null;

        // ── Reuse the Livewire page's PUBLIC data helpers ─────────────────────
        /** @var UniformStockFlow $page */
        $page = app(UniformStockFlow::class);
        $page->date_from   = $dateFrom;
        $page->date_to     = $dateTo;
        $page->category_id = $categoryId;
        $page->item_id     = $itemId;
        $page->variant_id  = $variantId;

        $payload = [
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'sections'    => $sections,
            'category_id' => $categoryId,
            'item_id'     => $itemId,
            'variant_id'  => $variantId,
            'metrics'     => $page->getMetrics(),
            'chart_data'  => $page->getFlowChartData(),
        ];

        $savedTab = $page->summary_tab;

        if (in_array('issuances', $sections)) {
            $page->summary_tab           = 'item';
            $payload['issuance_by_item'] = $page->getIssuanceSummary();
        }
        if (in_array('issuance_by_site', $sections)) {
            $page->summary_tab           = 'site';
            $payload['issuance_by_site'] = $page->getIssuanceSummary();
        }
        if (in_array('issuance_by_person', $sections)) {
            $page->summary_tab             = 'person';
            $payload['issuance_by_person'] = $page->getIssuanceSummary();
        }
        $page->summary_tab = $savedTab;

        if (in_array('stock_summary', $sections)) {
            $payload['stock_summary'] = $page->getStockSummary();
        }

        // ── Restocks & Returns — inlined here to avoid calling private methods ─
        if (in_array('restocks', $sections)) {
            $payload['restocks'] = self::getRestockSummary($dateFrom, $dateTo, $categoryId, $itemId, $variantId);
        }

        if (in_array('returns', $sections)) {
            $payload['returns'] = self::getReturnSummary($dateFrom, $dateTo, $categoryId, $itemId, $variantId);
        }

        return response(
            UniformStockFlowReportService::generate($payload),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    // ── Restock query (copied from UniformStockFlow — no longer needs to be public there) ──

    private static function getRestockSummary(
        string $from,
        string $to,
        ?string $categoryId,
        ?string $itemId,
        ?string $variantId
    ): array {
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

        if ($itemId)     $q->where('uniform_restock_items.uniform_item_id', $itemId);
        if ($variantId)  $q->where('uniform_restock_items.uniform_item_variant_id', $variantId);
        if ($categoryId) $q->where('uniform_items.uniform_category_id', $categoryId);

        return $q->get()->toArray();
    }

    // ── Return query (copied from UniformStockFlow — no longer needs to be public there) ──

    private static function getReturnSummary(
        string $from,
        string $to,
        ?string $categoryId,
        ?string $itemId,
        ?string $variantId
    ): array {
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

        if ($itemId)     $q->where('return_uniform_item_lines.uniform_item_id', $itemId);
        if ($variantId)  $q->where('return_uniform_item_lines.uniform_item_variant_id', $variantId);
        if ($categoryId) $q->where('uniform_items.uniform_category_id', $categoryId);

        return $q->get()->toArray();
    }
}