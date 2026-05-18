<?php

namespace App\Filament\Resources\UniformIssuances\Pages;

use App\Filament\Resources\UniformIssuances\UniformIssuancesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Models\UniformIssuanceLog;
use App\Models\UniformItemVariants;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ListUniformIssuances extends ListRecords
{
    protected static string $resource = UniformIssuancesResource::class;

    // ─────────────────────────────────────────────────────────────────────────
    // Stock helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Aggregate total quantity per variant across ALL recipients and items.
     * Returns [variantId => totalOrdered].
     */
    private function aggregateStock(array $data): array
    {
        $aggregate = [];

        foreach ($data['uniformIssuanceRecipient'] ?? [] as $recipient) {
            foreach ($recipient['uniformIssuanceItem'] ?? [] as $item) {
                $variantId = $item['uniform_item_variant_id'] ?? null;
                $qty       = (int) ($item['quantity'] ?? 0);
                if (!$variantId || $qty <= 0) continue;
                $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
            }
        }

        return $aggregate;
    }

    /**
     * Returns an array of stock issue details, empty if no issues.
     * Each entry: ['variant', 'ordered', 'stock', 'shortfall']
     */
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
                ->extraAttributes([
                    'style' => 'color: #ffffff;',
                ])

                // ── Pre-save stock validation ──────────────────────────────
                ->before(function (CreateAction $action, array $data): void {
                    $status = $data['uniform_issuance_status'] ?? 'pending';
                    $issues = $this->getStockIssues($data);

                    // ── HARD BLOCK: issued + insufficient stock ────────────
                    // Cannot save at all — notify and keep the modal open.
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

                    // ── SOFT BLOCK: pending + stock issues + not acknowledged
                    // The form shows a warning banner and a toggle. If the user
                    // hasn't ticked it yet, halt and tell them what to do.
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
                        // Acknowledged → fall through to save
                    }
                })

                // ── Post-save: sync quantities + logging + stock deduction ──
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

                        // ── STEP 1: compute total deductions per variant ──────
                        $plannedDeductions = [];
                        foreach ($record->uniformIssuanceRecipient as $recipient) {
                            foreach ($recipient->uniformIssuanceItem as $item) {
                                $qty = (int) $item->quantity;
                                if ($qty <= 0) continue;
                                $variantId = $item->uniform_item_variant_id;
                                $plannedDeductions[$variantId] = ($plannedDeductions[$variantId] ?? 0) + $qty;
                            }
                        }

                        // ── STEP 2: capture stock_before per variant BEFORE decrement ─
                        $stockBeforeMap = [];
                        foreach ($plannedDeductions as $variantId => $_) {
                            $variant = UniformItemVariants::find($variantId);
                            $stockBeforeMap[$variantId] = $variant ? (int) $variant->uniform_item_quantity : null;
                        }

                        // ── STEP 3: decrement each variant once ──────────────
                        foreach ($plannedDeductions as $variantId => $totalQty) {
                            UniformItemVariants::where('id', $variantId)
                                ->decrement('uniform_item_quantity', $totalQty);
                        }

                        // ── STEP 4: build note rows ───────────────────────────
                        $noteRows       = [];
                        $variantCache   = [];
                        $variantRunning = [];

                        foreach ($record->uniformIssuanceRecipient as $recipient) {
                            foreach ($recipient->uniformIssuanceItem as $item) {
                                $qty = (int) $item->quantity;
                                if ($qty <= 0) continue;

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

                        // Sort: lowest stock_after first
                        usort($noteRows, fn ($a, $b) => ($a['stock_after'] ?? 0) <=> ($b['stock_after'] ?? 0));

                        // Stamp issued_at
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