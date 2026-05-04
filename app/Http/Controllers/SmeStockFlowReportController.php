<?php

namespace App\Http\Controllers;

use App\Filament\Pages\SmeStockFlow;
use App\Services\SmeStockFlowReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SmeStockFlowReportController extends Controller
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
        $siteId     = $request->input('site_id')     ?: null;

        /** @var SmeStockFlow $page */
        $page = app(SmeStockFlow::class);
        $page->date_from   = $dateFrom;
        $page->date_to     = $dateTo;
        $page->category_id = $categoryId;
        $page->item_id     = $itemId;
        $page->variant_id  = $variantId;
        $page->site_id     = $siteId;

        $payload = [
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'sections'    => $sections,
            'category_id' => $categoryId,
            'item_id'     => $itemId,
            'variant_id'  => $variantId,
            'site_id'     => $siteId,
            'metrics'     => $page->getMetrics(),
            'chart_data'  => $page->getFlowChartData(),
        ];

        $savedTab = $page->summary_tab;

        if (in_array('purchase_orders', $sections)) {
            $page->summary_tab            = 'item';
            $payload['po_by_item']        = $page->getIssuanceSummary();
        }
        if (in_array('po_by_site', $sections)) {
            $page->summary_tab            = 'site';
            $payload['po_by_site']        = $page->getIssuanceSummary();
        }
        if (in_array('po_by_number', $sections)) {
            $page->summary_tab            = 'po';
            $payload['po_by_number']      = $page->getIssuanceSummary();
        }
        $page->summary_tab = $savedTab;

        if (in_array('stock_summary', $sections)) {
            $payload['stock_summary'] = $page->getStockSummary();
        }

        if (in_array('restocks', $sections)) {
            $payload['restocks'] = self::getRestockSummary($dateFrom, $dateTo, $categoryId, $itemId, $variantId);
        }

        if (in_array('returns', $sections)) {
            $payload['returns'] = self::getReturnSummary($dateFrom, $dateTo, $categoryId, $itemId, $variantId);
        }

        return response(
            SmeStockFlowReportService::generate($payload),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    private static function getRestockSummary(
        string $from, string $to,
        ?string $categoryId, ?string $itemId, ?string $variantId
    ): array {
        $q = \App\Models\SmeRestockItems::query()
            ->join('sme_restocks', 'sme_restocks.id', '=', 'sme_restock_items.sme_restock_id')
            ->join('sme_items', 'sme_items.id', '=', 'sme_restock_items.sme_item_id')
            ->join('sme_categories', 'sme_categories.id', '=', 'sme_items.sme_category_id')
            ->leftJoin('sme_item_variants', 'sme_item_variants.id', '=', 'sme_restock_items.sme_item_variant_id')
            ->whereIn('sme_restocks.status', ['delivered', 'partial'])
            ->whereBetween('sme_restocks.delivered_at', [$from, $to])
            ->select(
                'sme_items.sme_item_name as item_name',
                'sme_categories.sme_category_name as category_name',
                'sme_item_variants.sme_item_size as size',
                'sme_restock_items.delivered_quantity',
                'sme_restocks.delivered_at',
            )
            ->orderBy('sme_restocks.delivered_at');

        if ($itemId)     $q->where('sme_restock_items.sme_item_id', $itemId);
        if ($variantId)  $q->where('sme_restock_items.sme_item_variant_id', $variantId);
        if ($categoryId) $q->where('sme_items.sme_category_id', $categoryId);

        return $q->get()->toArray();
    }

    private static function getReturnSummary(
        string $from, string $to,
        ?string $categoryId, ?string $itemId, ?string $variantId
    ): array {
        $q = \App\Models\ReturnSmeItemLine::query()
            ->join('return_sme_items', 'return_sme_items.id', '=', 'return_sme_item_lines.return_sme_item_id')
            ->join('sme_items', 'sme_items.id', '=', 'return_sme_item_lines.sme_item_id')
            ->join('sme_categories', 'sme_categories.id', '=', 'sme_items.sme_category_id')
            ->leftJoin('sme_item_variants', 'sme_item_variants.id', '=', 'return_sme_item_lines.sme_item_variant_id')
            ->where('return_sme_item_lines.add_to_stock', true)
            ->where('return_sme_items.status', 'returned')
            ->whereBetween('return_sme_items.returned_at', [$from, $to])
            ->select(
                'sme_items.sme_item_name as item_name',
                'sme_categories.sme_category_name as category_name',
                'sme_item_variants.sme_item_size as size',
                'return_sme_item_lines.returned_quantity',
                'return_sme_items.returned_at',
            )
            ->orderBy('return_sme_items.returned_at');

        if ($itemId)     $q->where('return_sme_item_lines.sme_item_id', $itemId);
        if ($variantId)  $q->where('return_sme_item_lines.sme_item_variant_id', $variantId);
        if ($categoryId) $q->where('sme_items.sme_category_id', $categoryId);

        return $q->get()->toArray();
    }
}