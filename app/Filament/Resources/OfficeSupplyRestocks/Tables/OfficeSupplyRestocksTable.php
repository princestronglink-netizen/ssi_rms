<?php

namespace App\Filament\Resources\OfficeSupplyRestocks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\HtmlString;
use App\Models\OfficeSupplyItemVariant;
use App\Models\OfficeSupplyRestockLog;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class OfficeSupplyRestocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier_name')
                    ->searchable(),
                TextColumn::make('ordered_by')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match($state) {
                        'pending'   => 'warning',
                        'partial'   => 'info',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'returned'  => 'danger',
                        default     => 'gray',
                    }),
                TextColumn::make('status_date')
                    ->label('Date')
                    ->date()
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderByRaw("
                            CASE status
                                WHEN 'pending'   THEN pending_at
                                WHEN 'partial'   THEN partial_at
                                WHEN 'delivered' THEN delivered_at
                                WHEN 'cancelled' THEN cancelled_at
                                WHEN 'returned'  THEN returned_at
                                ELSE NULL
                            END {$direction}
                        ");
                    })
                    ->getStateUsing(fn ($record) => match($record->status) {
                        'pending'   => $record->pending_at,
                        'partial'   => $record->partial_at,
                        'delivered' => $record->delivered_at,
                        'cancelled' => $record->cancelled_at,
                        'returned'  => $record->returned_at,
                        default     => null,
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([

                // ─── VIEW ──────────────────────────────────────────────────
                Action::make('view')
                    ->label('View')
                    ->color('gray')
                    ->icon('heroicon-o-eye')
                    ->modalWidth('3xl')
                    ->modalContent(function ($record) {
                        $record->loadMissing(
                            'officeSupplyRestockItem.officeSupplyItem',
                            'officeSupplyRestockItem.officeSupplyItemVariant'
                        );

                        $rows     = '';
                        $totalQty = 0;
                        $totalDel = 0;
                        $totalRem = 0;

                        foreach ($record->officeSupplyRestockItem as $i => $item) {
                            $itemName  = e($item->officeSupplyItem?->office_supply_name ?? '—');
                            $variant   = e($item->officeSupplyItemVariant?->office_supply_variant ?? '—');
                            $qty       = (int) $item->quantity;
                            $delivered = (int) $item->delivered_quantity;
                            $remaining = (int) $item->remaining_quantity;

                            $totalQty += $qty;
                            $totalDel += $delivered;
                            $totalRem += $remaining;

                            $delColor = $delivered > 0 ? '#16a34a' : '#9ca3af';
                            $remColor = $remaining > 0 ? '#d97706' : '#9ca3af';
                            $bg       = $i % 2 === 0 ? '#ffffff' : '#f8fafc';

                            $rows .= "
                                <tr style='background:{$bg};'>
                                    <td style='padding:7px 10px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#111827;font-weight:500;'>{$itemName}</td>
                                    <td style='padding:7px 10px;border-bottom:1px solid #e5e7eb;font-size:12px;text-align:center;color:#374151;'>{$variant}</td>
                                    <td style='padding:7px 10px;border-bottom:1px solid #e5e7eb;font-size:12px;font-weight:700;text-align:center;color:#1d4ed8;'>{$qty}</td>
                                    <td style='padding:7px 10px;border-bottom:1px solid #e5e7eb;font-size:12px;font-weight:700;text-align:center;color:{$delColor};'>{$delivered}</td>
                                    <td style='padding:7px 10px;border-bottom:1px solid #e5e7eb;font-size:12px;font-weight:700;text-align:center;color:{$remColor};'>{$remaining}</td>
                                </tr>";
                        }

                        $rows .= "
                            <tr style='background:#eff6ff;border-top:2px solid #93c5fd;'>
                                <td colspan='2' style='padding:6px 10px;font-size:11px;font-weight:700;color:#374151;text-align:right;border-right:1px solid #cbd5e1;'>TOTAL</td>
                                <td style='padding:6px 10px;font-size:13px;font-weight:900;color:#1d4ed8;text-align:center;border-right:1px solid #cbd5e1;'>{$totalQty}</td>
                                <td style='padding:6px 10px;font-size:13px;font-weight:900;color:#16a34a;text-align:center;border-right:1px solid #cbd5e1;'>{$totalDel}</td>
                                <td style='padding:6px 10px;font-size:13px;font-weight:900;color:#d97706;text-align:center;'>{$totalRem}</td>
                            </tr>";

                        $supplier  = e($record->supplier_name);
                        $orderedBy = e($record->ordered_by);
                        $orderedAt = \Carbon\Carbon::parse($record->ordered_at)->format('M d, Y');
                        $notes     = e($record->notes ?? '—');

                        return new HtmlString("
                            <div style='margin-bottom:14px;padding:10px 14px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;font-size:12px;color:#374151;'>
                                <div><strong>Supplier:</strong> {$supplier}</div>
                                <div style='margin-top:4px;'><strong>Ordered By:</strong> {$orderedBy}</div>
                                <div style='margin-top:4px;'><strong>Order Date:</strong> {$orderedAt}</div>
                                <div style='margin-top:4px;'><strong>Notes:</strong> {$notes}</div>
                            </div>
                            <table style='width:100%;border-collapse:collapse;'>
                                <thead>
                                    <tr style='background:#1e3a5f;'>
                                        <th style='padding:7px 10px;text-align:left;font-size:10px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.05em;'>Item</th>
                                        <th style='padding:7px 10px;text-align:center;font-size:10px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.05em;width:80px;'>Variant</th>
                                        <th style='padding:7px 10px;text-align:center;font-size:10px;font-weight:700;color:#93c5fd;text-transform:uppercase;letter-spacing:.05em;width:60px;'>Qty</th>
                                        <th style='padding:7px 10px;text-align:center;font-size:10px;font-weight:700;color:#86efac;text-transform:uppercase;letter-spacing:.05em;width:80px;'>Delivered</th>
                                        <th style='padding:7px 10px;text-align:center;font-size:10px;font-weight:700;color:#fcd34d;text-transform:uppercase;letter-spacing:.05em;width:80px;'>Remaining</th>
                                    </tr>
                                </thead>
                                <tbody>{$rows}</tbody>
                            </table>
                        ");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // ─── EDIT: only when pending ───────────────────────────────
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),

                // ─── DELIVER: pending or partial ───────────────────────────
                Action::make('deliver')
                    ->label('Deliver')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->modalWidth('2xl')
                    ->visible(fn ($record) => in_array($record->status, ['pending', 'partial']))
                    ->form(function ($record) {
                        $fields = [];

                        foreach ($record->officeSupplyRestockItem as $item) {
                            // fallback to quantity if remaining_quantity is 0 (fresh pending records)
                            $remaining = (int) $item->remaining_quantity > 0
                                ? (int) $item->remaining_quantity
                                : (int) $item->quantity;

                            if ($remaining <= 0) continue;

                            $itemName = $item->officeSupplyItem?->office_supply_name ?? '—';
                            $variant  = $item->officeSupplyItemVariant?->office_supply_variant ?? '—';

                            $fields[] = TextInput::make("item_{$item->id}_deliver")
                                ->label("{$itemName} — {$variant} (remaining: {$remaining})")
                                ->numeric()
                                ->default($remaining)
                                ->minValue(0)
                                ->maxValue($remaining)
                                ->required();
                        }

                        return $fields;
                    })
                    ->action(function ($record, array $data) {
                        $totalDelivered = 0;
                        $totalRemaining = 0;
                        $note           = [];
                        $statusBefore   = $record->status;

                        foreach ($record->officeSupplyRestockItem as $item) {
                            $deliver = (int) ($data["item_{$item->id}_deliver"] ?? 0);

                            // fallback to quantity if remaining_quantity is 0 (fresh pending records)
                            $currentRemaining = (int) $item->remaining_quantity > 0
                                ? (int) $item->remaining_quantity
                                : (int) $item->quantity;

                            if ($deliver > 0) {
                                $item->update([
                                    'delivered_quantity' => (int) $item->delivered_quantity + $deliver,
                                    'remaining_quantity' => max(0, $currentRemaining - $deliver),
                                ]);

                                $variant = OfficeSupplyItemVariant::find($item->office_supply_item_variant_id);
                                if ($variant) {
                                    $stockBefore = (int) $variant->office_supply_quantity;
                                    $variant->increment('office_supply_quantity', $deliver);
                                    $stockAfter = $stockBefore + $deliver;
                                } else {
                                    $stockBefore = null;
                                    $stockAfter  = null;
                                }

                                $note[] = [
                                    'label'        => "{$item->officeSupplyItem?->office_supply_name} ({$item->officeSupplyItemVariant?->office_supply_variant})",
                                    'delivered'    => $deliver,
                                    'stock_before' => $stockBefore,
                                    'stock_after'  => $stockAfter,
                                ];
                            }

                            $item->refresh();
                            $totalDelivered += (int) $item->delivered_quantity;
                            $totalRemaining += (int) $item->remaining_quantity;
                        }

                        if ($totalRemaining === 0) {
                            $newStatus = 'delivered';
                        } elseif ($totalDelivered === 0) {
                            $newStatus = 'pending';
                        } else {
                            $newStatus = 'partial';
                        }

                        $record->update([
                            'status'       => $newStatus,
                            'delivered_at' => $newStatus === 'delivered' ? now()->toDateString() : null,
                            'partial_at'   => $newStatus === 'partial'   ? now()->toDateString() : null,
                        ]);

                        OfficeSupplyRestockLog::create([
                            'office_supply_restock_id' => $record->id,
                            'user_id'                  => Auth::id(),
                            'action'                   => $newStatus,
                            'status_from'              => $statusBefore,
                            'status_to'                => $newStatus,
                            'note'                     => json_encode($note),
                        ]);

                        if ($newStatus === 'delivered') {
                            Notification::make()
                                ->title('Fully Delivered')
                                ->body('All items delivered and added to inventory.')
                                ->success()
                                ->send();
                        } elseif ($newStatus === 'partial') {
                            Notification::make()
                                ->title('Partially Delivered')
                                ->body('Some items delivered. Remaining still pending.')
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No Items Delivered')
                                ->body('No quantities were entered. Nothing was updated.')
                                ->danger()
                                ->send();
                        }
                    }),

                // ─── RETURN ITEM: partial or delivered ─────────────────────
                Action::make('return_item')
                    ->label('Return')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->modalWidth('2xl')
                    ->visible(fn ($record) => in_array($record->status, ['partial', 'delivered']))
                    ->form(function ($record) {
                        $itemOptions = [];
                        foreach ($record->officeSupplyRestockItem as $item) {
                            $delivered = (int) $item->delivered_quantity;
                            if ($delivered <= 0) continue;
                            $itemOptions[$item->id] =
                                "{$item->officeSupplyItem?->office_supply_name} ({$item->officeSupplyItemVariant?->office_supply_variant}) — delivered: {$delivered}";
                        }

                        return [
                            Repeater::make('returns')
                                ->label('Items to Return')
                                ->addActionLabel('+ Add Another Return')
                                ->minItems(1)
                                ->defaultItems(1)
                                ->columnSpanFull()
                                ->schema([
                                    Select::make('restock_item_id')
                                        ->label('Item')
                                        ->options($itemOptions)
                                        ->required()
                                        ->searchable(),

                                    TextInput::make('return_qty')
                                        ->label('Quantity to Return')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),

                                    Select::make('reason')
                                        ->label('Reason')
                                        ->options([
                                            'defective'  => 'Defective',
                                            'wrong_item' => 'Wrong Item',
                                            'wrong_size' => 'Wrong Variant',
                                            'damaged'    => 'Damaged',
                                            'other'      => 'Other',
                                        ])
                                        ->required(),

                                    TextInput::make('remarks')
                                        ->label('Remarks (optional)')
                                        ->maxLength(255),
                                ]),
                        ];
                    })
                    ->action(function ($record, array $data, Action $action) {
                        $returns = $data['returns'] ?? [];

                        if (empty($returns)) {
                            Notification::make()->title('No Returns')->warning()->send();
                            $action->halt();
                            return;
                        }

                        // ── Validation ─────────────────────────────────────
                        foreach ($returns as $idx => $row) {
                            $restockItemId = (int) ($row['restock_item_id'] ?? 0);
                            $returnQty     = (int) ($row['return_qty'] ?? 0);
                            $restockItem   = $record->officeSupplyRestockItem->firstWhere('id', $restockItemId);

                            if (!$restockItem) {
                                Notification::make()->title("Row " . ($idx + 1) . ": Item not found")->danger()->send();
                                $action->halt();
                                return;
                            }

                            if ($returnQty > (int) $restockItem->delivered_quantity) {
                                Notification::make()
                                    ->title('Return Qty Exceeds Delivered')
                                    ->body("'{$restockItem->officeSupplyItem?->office_supply_name}' only has {$restockItem->delivered_quantity} delivered.")
                                    ->danger()
                                    ->send();
                                $action->halt();
                                return;
                            }

                            $variant = OfficeSupplyItemVariant::find($restockItem->office_supply_item_variant_id);
                            if ($variant && (int) $variant->office_supply_quantity < $returnQty) {
                                Notification::make()
                                    ->title('Insufficient Inventory')
                                    ->body("'{$restockItem->officeSupplyItem?->office_supply_name}' only has {$variant->office_supply_quantity} in stock.")
                                    ->danger()
                                    ->send();
                                $action->halt();
                                return;
                            }
                        }

                        // ── Apply ──────────────────────────────────────────
                        $noteRows     = [];
                        $statusBefore = $record->status;

                        foreach ($returns as $row) {
                            $restockItemId = (int) ($row['restock_item_id'] ?? 0);
                            $returnQty     = (int) ($row['return_qty'] ?? 0);
                            $reason        = $row['reason'] ?? 'other';
                            $remarks       = $row['remarks'] ?? '';
                            $restockItem   = $record->officeSupplyRestockItem->firstWhere('id', $restockItemId);
                            if (!$restockItem) continue;

                            $restockItem->update([
                                'delivered_quantity' => max(0, (int) $restockItem->delivered_quantity - $returnQty),
                                'remaining_quantity' => (int) $restockItem->remaining_quantity + $returnQty,
                            ]);

                            $variant = OfficeSupplyItemVariant::find($restockItem->office_supply_item_variant_id);
                            if ($variant) {
                                $stockBefore = (int) $variant->office_supply_quantity;
                                $variant->decrement('office_supply_quantity', $returnQty);
                                $stockAfter = $stockBefore - $returnQty;
                            } else {
                                $stockBefore = null;
                                $stockAfter  = null;
                            }

                            $noteRows[] = [
                                'label'        => "{$restockItem->officeSupplyItem?->office_supply_name} ({$restockItem->officeSupplyItemVariant?->office_supply_variant})",
                                'qty'          => $returnQty,
                                'reason'       => $reason,
                                'remarks'      => $remarks,
                                'stock_before' => $stockBefore,
                                'stock_after'  => $stockAfter,
                            ];
                        }

                        // ── Check if ALL items are fully returned ──────────
                        $record->load('officeSupplyRestockItem');
                        $allReturned = $record->officeSupplyRestockItem->every(
                            fn ($item) => (int) $item->delivered_quantity === 0
                        );

                        $newStatus = $allReturned ? 'returned' : $statusBefore;

                        $record->update([
                            'status'      => $newStatus,
                            'returned_at' => $allReturned ? now()->toDateString() : null,
                        ]);

                        // ── Log ────────────────────────────────────────────
                        OfficeSupplyRestockLog::create([
                            'office_supply_restock_id' => $record->id,
                            'user_id'                  => Auth::id(),
                            'action'                   => 'returned',
                            'status_from'              => $statusBefore,
                            'status_to'                => $newStatus,
                            'note'                     => json_encode($noteRows),
                        ]);

                        if ($allReturned) {
                            Notification::make()
                                ->title('Fully Returned')
                                ->body('All delivered items have been returned. Status set to Returned.')
                                ->danger()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Items Returned')
                                ->body(count($noteRows) . ' item(s) returned and deducted from inventory.')
                                ->warning()
                                ->send();
                        }
                    }),

                // ─── CANCEL: only when pending ─────────────────────────────
                Action::make('cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(function ($record) {
                        $statusBefore = $record->status;

                        $record->update([
                            'status'       => 'cancelled',
                            'cancelled_at' => now()->toDateString(),
                        ]);

                        OfficeSupplyRestockLog::create([
                            'office_supply_restock_id' => $record->id,
                            'user_id'                  => Auth::id(),
                            'action'                   => 'cancelled',
                            'status_from'              => $statusBefore,
                            'status_to'                => 'cancelled',
                            'note'                     => 'Restock was cancelled.',
                        ]);

                        Notification::make()->title('Cancelled')->body('Restock has been cancelled.')->danger()->send();
                    }),

                // ─── LOGS ──────────────────────────────────────────────────
                Action::make('view_logs')
                    ->label('Logs')
                    ->color('gray')
                    ->icon('heroicon-o-clock')
                    ->modalContent(function ($record) {
                        $logs = OfficeSupplyRestockLog::where('office_supply_restock_id', $record->id)
                            ->with('user')
                            ->latest()
                            ->get();

                        $rows = $logs->map(function ($log) {
                            $user   = $log->user?->name ?? 'System';
                            $date   = \Carbon\Carbon::parse($log->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A');
                            $from   = $log->status_from ?? '—';
                            $to     = $log->status_to   ?? '—';
                            $action = strtoupper($log->action);

                            $badgeColor = match($log->action) {
                                'delivered' => '#16a34a',
                                'partial'   => '#d97706',
                                'cancelled' => '#dc2626',
                                'returned'  => '#7c3aed',
                                'created'   => '#0891b2',
                                'edited'    => '#6b7280',
                                default     => '#6b7280',
                            };

                            $itemsHtml = '';
                            if (!empty($log->note)) {
                                $noteData = json_decode($log->note, true);
                                if (is_array($noteData)) {
                                    if ($log->action === 'returned') {
                                        $itemsHtml = "<div style='margin-top:6px;padding:6px 8px;background:#faf5ff;border-radius:6px;border:1px solid #e9d5ff;'>";
                                        $itemsHtml .= "<div style='font-size:10px;font-weight:700;color:#6d28d9;margin-bottom:4px;'>ITEMS RETURNED:</div>";
                                        foreach ($noteData as $row) {
                                            $label       = e($row['label'] ?? '—');
                                            $qty         = (int) ($row['qty'] ?? 0);
                                            $reason      = e(ucfirst(str_replace('_', ' ', $row['reason'] ?? '—')));
                                            $remarks     = e($row['remarks'] ?? '');
                                            $stockBefore = isset($row['stock_before']) ? (int) $row['stock_before'] : null;
                                            $stockAfter  = isset($row['stock_after'])  ? (int) $row['stock_after']  : null;

                                            $stockHtml = '';
                                            if ($stockBefore !== null && $stockAfter !== null) {
                                                $stockHtml = "<span style='font-size:10px;color:#9ca3af;margin-left:6px;'>
                                                    stock: <span style='color:#374151;font-weight:600;'>{$stockBefore}</span>
                                                    → <span style='color:#dc2626;font-weight:700;'>{$stockAfter}</span>
                                                    <span style='color:#9ca3af;'>(-{$qty})</span>
                                                </span>";
                                            }

                                            $itemsHtml .= "
                                                <div style='font-size:11px;color:#374151;padding:4px 0;border-bottom:1px dashed #e9d5ff;'>
                                                    <div style='display:flex;justify-content:space-between;align-items:center;'>
                                                        <span>{$label}</span>
                                                        <span style='font-weight:700;color:#7c3aed;'>×{$qty}</span>
                                                    </div>
                                                    <div style='font-size:10px;color:#9ca3af;margin-top:1px;'>{$reason}" . ($remarks ? " — {$remarks}" : '') . "</div>
                                                    <div>{$stockHtml}</div>
                                                </div>";
                                        }
                                        $itemsHtml .= "</div>";
                                    } elseif (in_array($log->action, ['delivered', 'partial'])) {
                                        $itemsHtml = "<div style='margin-top:6px;padding:6px 8px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;'>";
                                        $itemsHtml .= "<div style='font-size:10px;font-weight:700;color:#166534;margin-bottom:4px;'>ITEMS DELIVERED:</div>";
                                        foreach ($noteData as $row) {
                                            $label       = e($row['label'] ?? '—');
                                            $delivered   = (int) ($row['delivered'] ?? 0);
                                            $stockBefore = isset($row['stock_before']) ? (int) $row['stock_before'] : null;
                                            $stockAfter  = isset($row['stock_after'])  ? (int) $row['stock_after']  : null;

                                            $stockHtml = '';
                                            if ($stockBefore !== null && $stockAfter !== null) {
                                                $stockHtml = "<span style='font-size:10px;color:#9ca3af;margin-left:6px;'>
                                                    stock: <span style='color:#374151;font-weight:600;'>{$stockBefore}</span>
                                                    → <span style='color:#16a34a;font-weight:700;'>{$stockAfter}</span>
                                                    <span style='color:#9ca3af;'>(+{$delivered})</span>
                                                </span>";
                                            }

                                            $itemsHtml .= "
                                                <div style='font-size:11px;color:#374151;padding:4px 0;border-bottom:1px dashed #d1fae5;'>
                                                    <div style='display:flex;justify-content:space-between;align-items:center;'>
                                                        <span>{$label}</span>
                                                        <span style='font-weight:700;color:#16a34a;'>×{$delivered}</span>
                                                    </div>
                                                    <div>{$stockHtml}</div>
                                                </div>";
                                        }
                                        $itemsHtml .= "</div>";
                                    } else {
                                        $itemsHtml = "<div style='margin-top:4px;font-size:11px;color:#6b7280;font-style:italic;'>" . e($log->note) . "</div>";
                                    }
                                } else {
                                    $itemsHtml = "<div style='margin-top:4px;font-size:11px;color:#6b7280;font-style:italic;'>" . e($log->note) . "</div>";
                                }
                            }

                            $statusLine = $from === $to
                                ? "<span style='color:#9ca3af;font-style:italic;'>no status change</span>"
                                : "{$from} → {$to}";

                            return "
                                <tr>
                                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                        <div style='font-size:11px;color:#374151;white-space:nowrap;'>{$date}</div>
                                        <div style='font-size:10px;color:#9ca3af;margin-top:2px;'>{$user}</div>
                                    </td>
                                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                        <span style='background:{$badgeColor};color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;'>{$action}</span>
                                    </td>
                                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                        <div style='font-size:11px;color:#374151;'>{$statusLine}</div>
                                        {$itemsHtml}
                                    </td>
                                </tr>";
                        })->implode('');

                        return new HtmlString("
                            <div style='max-height:500px;overflow-y:auto;'>
                                <table style='width:100%;border-collapse:collapse;'>
                                    <thead style='position:sticky;top:0;z-index:1;'>
                                        <tr style='background:#1e3a5f;'>
                                            <th style='padding:8px 10px;text-align:left;font-size:11px;color:#fff;white-space:nowrap;'>Date / By</th>
                                            <th style='padding:8px 10px;text-align:left;font-size:11px;color:#fff;'>Action</th>
                                            <th style='padding:8px 10px;text-align:left;font-size:11px;color:#fff;'>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                            </div>
                        ");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}