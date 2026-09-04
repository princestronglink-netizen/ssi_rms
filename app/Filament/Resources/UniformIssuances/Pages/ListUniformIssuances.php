<?php

namespace App\Filament\Resources\UniformIssuances\Pages;

use App\Filament\Resources\UniformIssuances\UniformIssuancesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Models\UniformIssuanceLog;
use App\Models\UniformItemVariants;
use App\Models\UniformIssuances;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ListUniformIssuances extends ListRecords
{
    protected static string $resource = UniformIssuancesResource::class;

    // ─────────────────────────────────────────────────────────────────────────
    // Stock helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function aggregateStock(array $data): array
    {
        $aggregate = [];
        foreach ($data['uniformIssuanceRecipient'] ?? [] as $recipient) {
            foreach ($recipient['itemGroups'] ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $variantId = $item['uniform_item_variant_id'] ?? null;
                    $qty       = (int) ($item['quantity'] ?? 0);
                    if (!$variantId || $qty <= 0) continue;
                    $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
                }
            }
        }
        return $aggregate;
    }

    private function getStockIssues(array $data): array
    {
        $issues = [];

        foreach ($this->aggregateStock($data) as $variantId => $totalOrdered) {
            $variant = UniformItemVariants::find($variantId);
            if (!$variant) continue;

            $stock = (int) $variant->uniform_item_quantity;
            if ($totalOrdered > $stock) {
                $issues[] = [
                    'variant'   => $variant->uniform_item_size,
                    'ordered'   => $totalOrdered,
                    'stock'     => $stock,
                    'shortfall' => $totalOrdered - $stock,
                ];
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->extraAttributes([
                    'style' => 'color: #ffffff;',
                ])

                ->before(function (CreateAction $action, array $data): void {

                    // $data here is the ORIGINAL, unmutated submitted data —
                    // before() always runs pre-mutation, and since the
                    // repeater is no longer ->relationship()-bound, it's
                    // present in full, including nested itemGroups/items.
                    $status = $data['uniform_issuance_status'] ?? 'pending';
                    $issues = $this->getStockIssues($data);

                    if ($status === 'issued' && !empty($issues)) {
                        $lines = collect($issues)
                            ->map(fn ($i) => "• {$i['variant']}: ordered {$i['ordered']}, in stock {$i['stock']} (short by {$i['shortfall']})")
                            ->implode("\n");

                        Notification::make()
                            ->title('⛔ Cannot Issue — Insufficient Stock')
                            ->body(new HtmlString("
                                <div style='font-size:12px;color:#7f1d1d;margin-bottom:6px;'>
                                    The following items do not have enough stock.
                                    Reduce quantities or change status to <strong>Pending</strong>.
                                </div>
                                <pre style='font-size:11px;color:#991b1b;white-space:pre-wrap;margin:0;'>{$lines}</pre>
                            "))
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->halt();
                        return;
                    }

                    if ($status === 'pending' && !empty($issues)) {
                        $acknowledged = (bool) ($data['stock_warning_acknowledged'] ?? false);

                        if (!$acknowledged) {
                            Notification::make()
                                ->title('⚠️ Stock Warning — Confirmation Required')
                                ->body('Some items exceed available stock. Please tick the confirmation toggle at the bottom of the form before saving.')
                                ->warning()
                                ->persistent()
                                ->send();

                            $action->halt();
                            return;
                        }
                    }
                })

                // ── FULL CONTROL over record creation ──────────────────────
                // $data passed to ->using() is the ORIGINAL submitted data —
                // no mutation has happened to it yet, so
                // 'uniformIssuanceRecipient' with its nested itemGroups/items
                // is guaranteed to be present here.
                ->using(function (array $data): UniformIssuances {

                    $record = UniformIssuances::create(
                        collect($data)
                            ->except(['uniformIssuanceRecipient', 'stock_warning_acknowledged'])
                            ->toArray()
                    );

                    foreach ($data['uniformIssuanceRecipient'] ?? [] as $recipientData) {
                        $recipientRecord = $record->uniformIssuanceRecipient()->create(
                            collect($recipientData)->except(['itemGroups'])->toArray()
                        );

                        foreach ($recipientData['itemGroups'] ?? [] as $group) {
                            $typeId = $group['uniform_issuance_type_id'] ?? null;

                            foreach ($group['items'] ?? [] as $itemData) {
                                $qty = (int) ($itemData['quantity'] ?? 0);
                                if ($qty <= 0) continue;

                                $recipientRecord->uniformIssuanceItem()->create([
                                    'uniform_issuance_type_id' => $typeId,
                                    'uniform_item_id'          => $itemData['uniform_item_id'] ?? null,
                                    'uniform_item_variant_id'  => $itemData['uniform_item_variant_id'] ?? null,
                                    'quantity'                 => $qty,
                                    'released_quantity'        => 0,
                                    'remaining_quantity'       => $qty,
                                ]);
                            }
                        }
                    }

                    return $record;
                })

                ->after(function ($record) {

                    // No $data param needed here anymore — everything this
                    // block needs is loaded fresh off $record, which is now
                    // guaranteed to have its recipients/items already saved
                    // by ->using() above.

                    UniformIssuancesResource::syncQuantities($record);

                    UniformIssuanceLog::create([
                        'uniform_issuance_id' => $record->id,
                        'user_id'             => Auth::id(),
                        'action'              => 'created',
                        'status_from'         => null,
                        'status_to'           => $record->uniform_issuance_status,
                        'note'                => 'Issuance was created.',
                    ]);

                    if ($record->uniform_issuance_status === 'issued') {

                        $record->load(
                            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                            'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                            'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                        );

                        $plannedDeductions = [];
                        foreach ($record->uniformIssuanceRecipient as $recipient) {
                            foreach ($recipient->uniformIssuanceItem as $item) {
                                $qty = (int) $item->quantity;
                                if ($qty <= 0) continue;
                                $variantId = $item->uniform_item_variant_id;
                                $plannedDeductions[$variantId] = ($plannedDeductions[$variantId] ?? 0) + $qty;
                            }
                        }

                        $stockBeforeMap = [];
                        foreach ($plannedDeductions as $variantId => $_) {
                            $variant = UniformItemVariants::find($variantId);
                            $stockBeforeMap[$variantId] = $variant ? (int) $variant->uniform_item_quantity : null;
                        }

                        foreach ($plannedDeductions as $variantId => $totalQty) {
                            UniformItemVariants::where('id', $variantId)
                                ->decrement('uniform_item_quantity', $totalQty);
                        }

                        $noteRows       = [];
                        $variantCache   = [];
                        $variantRunning = [];

                        foreach ($record->uniformIssuanceRecipient as $recipient) {
                            foreach ($recipient->uniformIssuanceItem as $item) {
                                $qty = (int) $item->quantity;
                                if ($qty <= 0) continue;

                                $item->update([
                                    'released_quantity'  => $qty,
                                    'remaining_quantity' => 0,
                                ]);

                                $variantId = $item->uniform_item_variant_id;

                                if (!isset($variantCache[$variantId])) {
                                    $variantCache[$variantId] = UniformItemVariants::find($variantId);
                                }

                                $variant         = $variantCache[$variantId];
                                $stockFinal      = $variant ? (int) $variant->uniform_item_quantity : 0;
                                $totalDeducted   = $plannedDeductions[$variantId] ?? 0;
                                $stockOriginal   = $stockFinal + $totalDeducted;

                                $alreadyConsumed = $variantRunning[$variantId] ?? 0;
                                $stockBefore     = $stockOriginal - $alreadyConsumed;
                                $stockAfter      = $stockBefore - $qty;

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

                        usort($noteRows, fn ($a, $b) => ($a['stock_after'] ?? 0) <=> ($b['stock_after'] ?? 0));

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