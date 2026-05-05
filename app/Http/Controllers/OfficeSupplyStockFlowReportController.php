<?php

namespace App\Http\Controllers;

use App\Filament\Pages\OfficeSupplyStockFlow;
use App\Services\OfficeSupplyStockFlowReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfficeSupplyStockFlowReportController extends Controller
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

        /** @var OfficeSupplyStockFlow $page */
        $page = app(OfficeSupplyStockFlow::class);
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

        if (in_array('requests', $sections)) {
            $page->summary_tab       = 'item';
            $payload['req_by_item']  = $page->getRequestSummary();
        }
        if (in_array('req_by_requester', $sections)) {
            $page->summary_tab             = 'requester';
            $payload['req_by_requester']   = $page->getRequestSummary();
        }
        if (in_array('req_by_number', $sections)) {
            $page->summary_tab           = 'request';
            $payload['req_by_number']    = $page->getRequestSummary();
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
            OfficeSupplyStockFlowReportService::generate($payload),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    private static function getRestockSummary(
        string $from, string $to,
        ?string $categoryId, ?string $itemId, ?string $variantId
    ): array {
        $q = \App\Models\OfficeSupplyRestockItem::query()
            ->join('office_supply_restocks', 'office_supply_restocks.id', '=', 'office_supply_restock_items.office_supply_restock_id')
            ->join('office_supply_items', 'office_supply_items.id', '=', 'office_supply_restock_items.office_supply_item_id')
            ->join('office_supply_categories', 'office_supply_categories.id', '=', 'office_supply_items.office_supply_category_id')
            ->leftJoin('office_supply_item_variants', 'office_supply_item_variants.id', '=', 'office_supply_restock_items.office_supply_item_variant_id')
            ->whereIn('office_supply_restocks.status', ['delivered', 'partial'])
            ->whereBetween('office_supply_restocks.delivered_at', [$from, $to])
            ->select(
                'office_supply_items.office_supply_name as item_name',
                'office_supply_categories.office_supply_category_name as category_name',
                'office_supply_item_variants.office_supply_variant as variant',
                'office_supply_restock_items.delivered_quantity',
                'office_supply_restocks.delivered_at',
            )
            ->orderBy('office_supply_restocks.delivered_at');

        if ($itemId)     $q->where('office_supply_restock_items.office_supply_item_id', $itemId);
        if ($variantId)  $q->where('office_supply_restock_items.office_supply_item_variant_id', $variantId);
        if ($categoryId) $q->where('office_supply_items.office_supply_category_id', $categoryId);

        return $q->get()->toArray();
    }

    private static function getReturnSummary(
        string $from, string $to,
        ?string $categoryId, ?string $itemId, ?string $variantId
    ): array {
        $q = \App\Models\ReturnOfficeSupplyItemLine::query()
            ->join('return_office_supply_items', 'return_office_supply_items.id', '=', 'return_office_supply_item_lines.return_office_supply_item_id')
            ->join('office_supply_items', 'office_supply_items.id', '=', 'return_office_supply_item_lines.office_supply_item_id')
            ->join('office_supply_categories', 'office_supply_categories.id', '=', 'office_supply_items.office_supply_category_id')
            ->leftJoin('office_supply_item_variants', 'office_supply_item_variants.id', '=', 'return_office_supply_item_lines.office_supply_item_variant_id')
            ->where('return_office_supply_item_lines.add_to_stock', true)
            ->where('return_office_supply_items.status', 'returned')
            ->whereBetween('return_office_supply_items.returned_at', [$from, $to])
            ->select(
                'office_supply_items.office_supply_name as item_name',
                'office_supply_categories.office_supply_category_name as category_name',
                'office_supply_item_variants.office_supply_variant as variant',
                'return_office_supply_item_lines.returned_quantity',
                'return_office_supply_items.returned_at',
            )
            ->orderBy('return_office_supply_items.returned_at');

        if ($itemId)     $q->where('return_office_supply_item_lines.office_supply_item_id', $itemId);
        if ($variantId)  $q->where('return_office_supply_item_lines.office_supply_item_variant_id', $variantId);
        if ($categoryId) $q->where('office_supply_items.office_supply_category_id', $categoryId);

        return $q->get()->toArray();
    }
}