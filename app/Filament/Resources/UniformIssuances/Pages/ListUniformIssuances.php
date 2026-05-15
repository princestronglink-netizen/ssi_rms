<?php

namespace App\Filament\Resources\UniformIssuances\Pages;

use App\Filament\Resources\UniformIssuances\UniformIssuancesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Models\UniformIssuanceLog;
use App\Models\UniformItemVariants;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ListUniformIssuances extends ListRecords
{
    protected static string $resource = UniformIssuancesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->extraAttributes([
                    'style' => 'color: #ffffff;'
                ])
                ->after(function ($record) {
                    // syncQuantities does a fresh ->load() internally, so
                    // all items get correct released/remaining values written to DB.
                    UniformIssuancesResource::syncQuantities($record);

                    // Always log creation
                    UniformIssuanceLog::create([
                        'uniform_issuance_id' => $record->id,
                        'user_id'             => Auth::id(),
                        'action'              => 'created',
                        'status_from'         => null,
                        'status_to'           => $record->uniform_issuance_status,
                        'note'                => 'Issuance was created.',
                    ]);

                    // If created directly as issued, deduct stock for EVERY item.
                    if ($record->uniform_issuance_status === 'issued') {

                        // Fresh load after syncQuantities updated the rows.
                        $record->load(
                            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                            'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                        );

                        // ── STEP 1: compute total deductions per variant ──────────────────
                        $plannedDeductions = [];
                        foreach ($record->uniformIssuanceRecipient as $recipient) {
                            foreach ($recipient->uniformIssuanceItem as $item) {
                                $qty = (int) $item->quantity;
                                if ($qty <= 0) continue;
                                $variantId = $item->uniform_item_variant_id;
                                $plannedDeductions[$variantId] = ($plannedDeductions[$variantId] ?? 0) + $qty;
                            }
                        }

                        // ── STEP 2: capture stock_before per variant BEFORE any decrement ─
                        $stockBeforeMap = [];
                        foreach ($plannedDeductions as $variantId => $_) {
                            $variant = UniformItemVariants::find($variantId);
                            $stockBeforeMap[$variantId] = $variant ? (int) $variant->uniform_item_quantity : null;
                        }

                        // ── STEP 3: decrement each variant once ──────────────────────────
                        foreach ($plannedDeductions as $variantId => $totalQty) {
                            UniformItemVariants::where('id', $variantId)
                                ->decrement('uniform_item_quantity', $totalQty);
                        }

                        // ── STEP 4: build note rows — distinct per item + type + employee ─
                        // We compute per-item stock_before/after using a running offset per variant.
                        $noteRows       = [];
                        $variantCache   = [];
                        $variantRunning = []; // tracks how much has been "consumed" so far per variant

                        foreach ($record->uniformIssuanceRecipient as $recipient) {
                            foreach ($recipient->uniformIssuanceItem as $item) {
                                $qty = (int) $item->quantity;
                                if ($qty <= 0) continue;

                                $variantId = $item->uniform_item_variant_id;

                                // Load fresh variant once per variant id after decrement
                                if (! isset($variantCache[$variantId])) {
                                    $variantCache[$variantId] = UniformItemVariants::find($variantId);
                                }

                                $variant         = $variantCache[$variantId];
                                $stockFinal      = $variant ? (int) $variant->uniform_item_quantity : 0; // after ALL deductions
                                $totalDeducted   = $plannedDeductions[$variantId] ?? 0;
                                $stockOriginal   = $stockFinal + $totalDeducted; // what it was before any deduction

                                // How much was already consumed by earlier rows for this variant
                                $alreadyConsumed = $variantRunning[$variantId] ?? 0;

                                $stockBefore = $stockOriginal - $alreadyConsumed;
                                $stockAfter  = $stockBefore   - $qty;

                                $variantRunning[$variantId] = $alreadyConsumed + $qty;

                                $typeName = $item->uniformIssuanceType?->uniform_issuance_type_name ?? '—';

                                $noteRows[] = [
                                    'label'        => ($item->uniformItem?->uniform_item_name ?? '—')
                                                    . ' (' . ($item->uniformItemVariant?->uniform_item_size ?? '—') . ')'
                                                    . ' [' . $typeName . ']'
                                                    . ' — ' . ($recipient->employee_name ?? '—'),
                                    'released'     => $qty,
                                    'stock_before' => $stockBefore,
                                    'stock_after'  => $stockAfter,
                                ];
                            }
                        }

                        // Sort by stock_after ascending (lowest first)
                        usort($noteRows, fn ($a, $b) => ($a['stock_after'] ?? 0) <=> ($b['stock_after'] ?? 0));

                        // Ensure issued_at is stamped
                        $record->update([
                            'issued_at' => $record->issued_at ?? now()->toDateString(),
                        ]);

                        UniformIssuanceLog::create([
                            'uniform_issuance_id' => $record->id,
                            'user_id'             => Auth::id(),
                            'action'              => 'issued',
                            'status_from'         => 'pending',
                            'status_to'           => 'issued',
                            'note'                => json_encode($noteRows),
                        ]);

                        $totalReleased = collect($noteRows)->sum('released');

                        Notification::make()
                            ->title('Issuance Created & Issued')
                            ->body("All items have been deducted from inventory. ({$totalReleased} pcs total)")
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}