<?php

namespace App\Filament\Resources\SmePurchaseOrders\Pages;

use App\Filament\Resources\SmePurchaseOrders\SmePurchaseOrderResource;
use App\Models\SmePurchaseOrder;
use App\Models\SmePurchaseOrderLog;
use App\Models\SmeItemVariants;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ListSmePurchaseOrders extends ListRecords
{
    protected static string $resource = SmePurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(function (SmePurchaseOrder $record): void {

                    // ── Log: created ──
                    SmePurchaseOrderLog::create([
                        'sme_purchase_order_id' => $record->id,
                        'user_id'               => Auth::id(),
                        'action'                => 'created',
                        'status_from'           => null,
                        'status_to'             => $record->status,
                        'note'                  => null,
                    ]);

                    // ── If created as approved, deduct stock ──
                    if ($record->status !== 'approved') {
                        return;
                    }

                    DB::transaction(function () use ($record): void {
                        $record->load('purchaseOrderItems.smeItem', 'purchaseOrderItems.smeItemVariant');

                        // ── PASS 1: compute total deductions per variant ──────────────────
                        $plannedDeductions = [];
                        foreach ($record->purchaseOrderItems as $item) {
                            $variantId = $item->sme_item_variant_id;
                            $plannedDeductions[$variantId] = ($plannedDeductions[$variantId] ?? 0) + (int) $item->quantity;
                        }

                        // ── PASS 2: validate stock ────────────────────────────────────────
                        foreach ($plannedDeductions as $variantId => $totalPlanned) {
                            $variant = \App\Models\SmeItemVariants::find($variantId);
                            if (! $variant || $variant->sme_item_quantity < $totalPlanned) {
                                throw new \Exception(
                                    "Insufficient stock for variant ID {$variantId}. " .
                                    "Available: " . ($variant?->sme_item_quantity ?? 0) . ", Required: {$totalPlanned}"
                                );
                            }
                        }

                        // ── PASS 3: capture stock_before per variant BEFORE any decrement ─
                        $stockBeforeMap = [];
                        foreach ($plannedDeductions as $variantId => $_) {
                            $variant = \App\Models\SmeItemVariants::find($variantId);
                            $stockBeforeMap[$variantId] = $variant ? (int) $variant->sme_item_quantity : 0;
                        }

                        // ── PASS 4: decrement each variant once ──────────────────────────
                        foreach ($plannedDeductions as $variantId => $totalPlanned) {
                            \App\Models\SmeItemVariants::where('id', $variantId)
                                ->decrement('sme_item_quantity', $totalPlanned);
                        }

                        // ── PASS 5: build note rows with per-item stock windows ───────────
                        $noteItems      = [];
                        $variantRunning = [];

                        foreach ($record->purchaseOrderItems as $item) {
                            $qty       = (int) $item->quantity;
                            $variantId = $item->sme_item_variant_id;

                            $alreadyConsumed = $variantRunning[$variantId] ?? 0;
                            $stockOriginal   = $stockBeforeMap[$variantId] ?? 0;

                            $stockBefore = $stockOriginal - $alreadyConsumed;
                            $stockAfter  = $stockBefore   - $qty;

                            $variantRunning[$variantId] = $alreadyConsumed + $qty;

                            $noteItems[] = [
                                'label'        => ($item->smeItem?->sme_item_name ?? '—') . ' (' . ($item->smeItemVariant?->sme_item_size ?? '—') . ')',
                                'qty'          => $qty,
                                'stock_before' => $stockBefore,
                                'stock_after'  => $stockAfter,
                            ];
                        }

                        // Sort: lowest stock_after first
                        usort($noteItems, fn ($a, $b) => ($a['stock_after'] ?? 0) <=> ($b['stock_after'] ?? 0));

                        $record->update(['approved_at' => now()]);

                        SmePurchaseOrderLog::create([
                            'sme_purchase_order_id' => $record->id,
                            'user_id'               => Auth::id(),
                            'action'                => 'approved',
                            'status_from'           => 'approved',
                            'status_to'             => 'approved',
                            'note'                  => $noteItems,
                        ]);
                    });
                }),
        ];
    }
}