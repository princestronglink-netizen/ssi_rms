<?php

namespace App\Filament\Resources\SmePurchaseOrders\Pages;

use App\Filament\Resources\SmePurchaseOrders\SmePurchaseOrderResource;
use App\Filament\Resources\SmePurchaseOrders\Schemas\SmePurchaseOrderForm;
use App\Models\SmePurchaseOrder;
use App\Models\SmePurchaseOrderLog;
use App\Models\SmeItemVariants;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ListSmePurchaseOrders extends ListRecords
{
    protected static string $resource = SmePurchaseOrderResource::class;

    // ─────────────────────────────────────────────────────────────────────────
    // Stock helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getStockIssues(array $data): array
    {
        $aggregate = [];

        foreach ($data['purchaseOrderItems'] ?? [] as $item) {
            $variantId = $item['sme_item_variant_id'] ?? null;
            $qty       = (int) ($item['quantity'] ?? 0);
            if (!$variantId || $qty <= 0) continue;
            $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
        }

        $issues = [];
        foreach ($aggregate as $variantId => $totalOrdered) {
            $variant = SmeItemVariants::find($variantId);
            if (!$variant) continue;

            $stock = (int) $variant->sme_item_quantity;
            if ($totalOrdered > $stock) {
                $issues[] = [
                    'variant'   => $variant->sme_item_size,
                    'ordered'   => $totalOrdered,
                    'stock'     => $stock,
                    'shortfall' => $totalOrdered - $stock,
                ];
            }
        }

        return $issues;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Persistence helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Inserts the PO header + line items manually.
     *
     * The Repeater has NO ->relationship() because this form lives inside a
     * modal Action on a ListRecords page (no parent model exists yet).
     * With ->relationship() Filament strips rows from $data before the
     * action closure runs, leaving purchaseOrderItems always empty.
     */
    private function createRecord(array $data): SmePurchaseOrder
    {
        $items = $data['purchaseOrderItems'] ?? [];

        $record = SmePurchaseOrder::create(
            collect($data)
                ->except(['purchaseOrderItems', 'stock_warning_acknowledged'])
                ->toArray()
        );

        foreach ($items as $item) {
            if (empty($item['sme_item_variant_id']) || empty($item['quantity'])) {
                continue;
            }

            $record->purchaseOrderItems()->create([
                'sme_item_id'         => $item['sme_item_id']        ?? null,
                'sme_item_variant_id' => $item['sme_item_variant_id'],
                'quantity'            => (int) $item['quantity'],
                'released_quantity'   => (int) ($item['released_quantity']  ?? 0),
                'remaining_quantity'  => (int) ($item['remaining_quantity'] ?? $item['quantity']),
            ]);
        }

        return $record;
    }

    /**
     * Post-save logic: logs the creation, and for approved orders
     * atomically deducts stock inside a DB transaction.
     */
    private function afterCreate(SmePurchaseOrder $record): void
    {
        if ($record->status !== 'approved') {
            SmePurchaseOrderLog::create([
                'sme_purchase_order_id' => $record->id,
                'user_id'               => Auth::id(),
                'action'                => 'created',
                'status_from'           => null,
                'status_to'             => $record->status,
                'note'                  => null,
            ]);
            return;
        }

        DB::transaction(function () use ($record): void {
            $record->load('purchaseOrderItems.smeItem', 'purchaseOrderItems.smeItemVariant');

            // PASS 1: aggregate total needed per variant
            $plannedDeductions = [];
            foreach ($record->purchaseOrderItems as $item) {
                $variantId = $item->sme_item_variant_id;
                $plannedDeductions[$variantId] = ($plannedDeductions[$variantId] ?? 0) + (int) $item->quantity;
            }

            // PASS 2: race-condition stock guard
            foreach ($plannedDeductions as $variantId => $totalPlanned) {
                $variant = SmeItemVariants::find($variantId);
                if (!$variant || $variant->sme_item_quantity < $totalPlanned) {
                    throw new \RuntimeException(
                        "Insufficient stock for variant ID {$variantId}. " .
                        "Available: " . ($variant?->sme_item_quantity ?? 0) . ", Required: {$totalPlanned}"
                    );
                }
            }

            // PASS 3: capture stock_before snapshots
            $stockBeforeMap = [];
            foreach ($plannedDeductions as $variantId => $_) {
                $v = SmeItemVariants::find($variantId);
                $stockBeforeMap[$variantId] = $v ? (int) $v->sme_item_quantity : 0;
            }

            // PASS 4: decrement each variant once
            foreach ($plannedDeductions as $variantId => $totalPlanned) {
                SmeItemVariants::where('id', $variantId)
                    ->decrement('sme_item_quantity', $totalPlanned);
            }

            // PASS 5: build per-item audit note rows
            $noteItems      = [];
            $variantRunning = [];

            foreach ($record->purchaseOrderItems as $item) {
                $qty       = (int) $item->quantity;
                $variantId = $item->sme_item_variant_id;

                $alreadyConsumed            = $variantRunning[$variantId] ?? 0;
                $stockBefore                = ($stockBeforeMap[$variantId] ?? 0) - $alreadyConsumed;
                $stockAfter                 = $stockBefore - $qty;
                $variantRunning[$variantId] = $alreadyConsumed + $qty;

                $noteItems[] = [
                    'label'        => ($item->smeItem?->sme_item_name ?? '—') . ' (' . ($item->smeItemVariant?->sme_item_size ?? '—') . ')',
                    'qty'          => $qty,
                    'stock_before' => $stockBefore,
                    'stock_after'  => $stockAfter,
                ];
            }

            usort($noteItems, fn ($a, $b) => ($a['stock_after'] ?? 0) <=> ($b['stock_after'] ?? 0));

            $record->update(['approved_at' => now()]);

            SmePurchaseOrderLog::create([
                'sme_purchase_order_id' => $record->id,
                'user_id'               => Auth::id(),
                'action'                => 'approved',
                'status_from'           => null,        // null = created directly as approved
                'status_to'             => 'approved',
                'note'                  => $noteItems,
            ]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Actions
    // ─────────────────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createOrder')
                ->label('New Purchase Order')
                ->icon('heroicon-o-plus')
                ->modalHeading('Create Purchase Order')
                ->modalSubmitActionLabel('Create')
                ->modalWidth('5xl')
                ->form(fn (\Filament\Schemas\Schema $schema) => SmePurchaseOrderForm::configure($schema))

                ->action(function (array $data, Action $action): void {
                    Log::debug('createOrder $data', $data);

                    $status = $data['status'] ?? 'pending';
                    $issues = $this->getStockIssues($data);

                    // ── HARD BLOCK: approved + insufficient stock ──────────────
                    // Cannot save at all — notify and keep the modal open.
                    if ($status === 'approved' && !empty($issues)) {
                        $lines = collect($issues)
                            ->map(fn ($i) => "• {$i['variant']}: ordered {$i['ordered']}, in stock {$i['stock']} (short by {$i['shortfall']})")
                            ->implode("\n");

                        Notification::make()
                            ->title('⛔ Cannot Approve — Insufficient Stock')
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

                    // ── SOFT BLOCK: pending + stock issues + not acknowledged ──
                    // The form shows a warning banner and a checkbox. If the user
                    // hasn't ticked it yet, halt and tell them what to do.
                    if ($status === 'pending' && !empty($issues)) {
                        $acknowledged = (bool) ($data['stock_warning_acknowledged'] ?? false);

                        if (!$acknowledged) {
                            Notification::make()
                                ->title('⚠️ Stock Warning — Confirmation Required')
                                ->body('Some items exceed available stock. Please tick the confirmation checkbox at the bottom of the form before saving.')
                                ->warning()
                                ->persistent()
                                ->send();

                            $action->halt();
                            return;
                        }
                        // Acknowledged → fall through to save below
                    }

                    // ── SAVE ──────────────────────────────────────────────────
                    try {
                        $record = $this->createRecord($data);
                        $this->afterCreate($record);
                    } catch (\RuntimeException $e) {
                        Log::error('createOrder race condition', ['error' => $e->getMessage()]);

                        Notification::make()
                            ->title('⛔ Stock changed during save')
                            ->body('Stock levels changed before the order could be saved. Please check inventory and try again.')
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->halt();
                        return;
                    }

                    Notification::make()
                        ->title(
                            $status === 'approved'
                                ? 'Purchase order created & approved'
                                : 'Purchase order saved as Pending'
                        )
                        ->body(
                            $status === 'pending' && !empty($issues)
                                ? 'Saved as Pending. Adjust stock before approving.'
                                : null
                        )
                        ->when($status === 'approved', fn ($n) => $n->success())
                        ->when($status === 'pending' && !empty($issues), fn ($n) => $n->warning())
                        ->when($status === 'pending' && empty($issues), fn ($n) => $n->success())
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}