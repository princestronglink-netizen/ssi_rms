<?php

namespace App\Filament\Resources\SmePurchaseOrders\Tables;

use App\Models\SmePurchaseOrder;
use App\Models\SmePurchaseOrderLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SmePurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.site_name')
                    ->label('Site')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    }),
                TextColumn::make('po_number')
                    ->searchable(),
                TextColumn::make('po_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status_date')
                    ->label('Status Date')
                    ->getStateUsing(fn (SmePurchaseOrder $record) => match ($record->status) {
                        'approved' => $record->approved_at,
                        'rejected' => $record->rejected_at,
                        default    => $record->pending_at,
                    })
                    ->dateTime()
                    ->sortable(query: function ($query, string $direction) {
                        $query->orderByRaw("COALESCE(approved_at, rejected_at, pending_at) $direction");
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
            ->filters([])
            ->recordActions([

                // ─── VIEW ─────────────────────────────────────────────────
                Action::make('view')
                    ->label('View')
                    ->color('gray')
                    ->icon('heroicon-o-eye')
                    ->modalWidth('3xl')
                    ->modalHeading(fn (SmePurchaseOrder $record) => 'Purchase Order — ' . ($record->site?->site_name ?? '—'))
                    ->modalContent(function (SmePurchaseOrder $record) {
                        $record->loadMissing('purchaseOrderItems.smeItem', 'purchaseOrderItems.smeItemVariant', 'site');

                        $siteName  = e($record->site?->site_name ?? '—');
                        $poNumber  = e($record->po_number ?? '—');
                        $poDate    = $record->po_date?->format('F d, Y') ?? '—';
                        $note      = e($record->note ?? '');

                        $statusColor = match ($record->status) {
                            'approved' => '#16a34a',
                            'rejected' => '#dc2626',
                            default    => '#d97706',
                        };
                        $statusLabel = strtoupper($record->status ?? 'PENDING');

                        $statusDate = match ($record->status) {
                            'approved' => $record->approved_at,
                            'rejected' => $record->rejected_at,
                            default    => $record->pending_at,
                        };
                        $statusDateFormatted = $statusDate
                            ? \Carbon\Carbon::parse($statusDate)->format('F d, Y h:i A')
                            : '—';

                        // ── Items table ──
                        $itemRows        = '';
                        $grandTotalQty   = 0;
                        $grandReleased   = 0;
                        $grandRemaining  = 0;

                        foreach ($record->purchaseOrderItems as $i => $item) {
                            $itemName  = e($item->smeItem?->sme_item_name ?? '—');
                            $size      = e($item->smeItemVariant?->sme_item_size ?? '—');
                            $qty       = (int) $item->quantity;
                            $released  = (int) $item->released_quantity;
                            $remaining = (int) $item->remaining_quantity;

                            $grandTotalQty  += $qty;
                            $grandReleased  += $released;
                            $grandRemaining += $remaining;

                            $releasedColor  = $released  > 0 ? '#16a34a' : '#9ca3af';
                            $remainingColor = $remaining > 0 ? '#d97706' : '#9ca3af';
                            $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';

                            $itemRows .= "
                                <tr style='background:{$bg};'>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#111827;font-weight:500;'>{$itemName}</td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#374151;text-align:center;'>{$size}</td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;text-align:center;color:#1d4ed8;'>{$qty}</td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;text-align:center;color:{$releasedColor};'>{$released}</td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;text-align:center;color:{$remainingColor};'>{$remaining}</td>
                                </tr>";
                        }

                        // Totals footer row
                        $itemRows .= "
                            <tr style='background:#eff6ff;border-top:2px solid #93c5fd;'>
                                <td colspan='2' style='padding:8px 14px;font-size:11px;font-weight:700;color:#374151;text-align:right;'>TOTAL</td>
                                <td style='padding:8px 14px;font-size:14px;font-weight:900;color:#1d4ed8;text-align:center;'>{$grandTotalQty}</td>
                                <td style='padding:8px 14px;font-size:14px;font-weight:900;color:#16a34a;text-align:center;'>{$grandReleased}</td>
                                <td style='padding:8px 14px;font-size:14px;font-weight:900;color:#d97706;text-align:center;'>{$grandRemaining}</td>
                            </tr>";

                        // ── Grand total bar ──
                        $grandTotalBar = "
                            <div style='border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:16px;'>
                                <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                    Summary
                                </div>
                                <div style='display:grid;grid-template-columns:1fr 1fr 1fr;'>
                                    <div style='padding:12px 14px;text-align:center;border-right:1px solid #f1f5f9;'>
                                        <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;'>Total Qty</div>
                                        <div style='font-size:20px;font-weight:900;color:#1d4ed8;'>{$grandTotalQty}</div>
                                    </div>
                                    <div style='padding:12px 14px;text-align:center;border-right:1px solid #f1f5f9;'>
                                        <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;'>Released</div>
                                        <div style='font-size:20px;font-weight:900;color:#16a34a;'>{$grandReleased}</div>
                                    </div>
                                    <div style='padding:12px 14px;text-align:center;'>
                                        <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;'>Remaining</div>
                                        <div style='font-size:20px;font-weight:900;color:#d97706;'>{$grandRemaining}</div>
                                    </div>
                                </div>
                            </div>";

                        // ── DR section (only if dr_number exists) ──
                        $drHtml = '';
                        if (!blank($record->dr_number)) {
                            $drNumber = e($record->dr_number);
                            $drFile   = $record->dr_file_path
                                ? "<a href='" . \Illuminate\Support\Facades\Storage::url($record->dr_file_path) . "'
                                        target='_blank'
                                        style='color:#2563eb;font-size:12px;text-decoration:underline;'>
                                        View File ↗
                                   </a>"
                                : "<span style='color:#9ca3af;font-size:12px;'>No file attached</span>";

                            $drHtml = "
                                <div style='border:1px solid #bbf7d0;border-radius:8px;overflow:hidden;margin-bottom:16px;'>
                                    <div style='background:#f0fdf4;padding:9px 14px;font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;letter-spacing:.06em;'>
                                        Delivery Receipt
                                    </div>
                                    <div style='display:grid;grid-template-columns:1fr 1fr;padding:12px 14px;gap:8px;'>
                                        <div>
                                            <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;'>DR Number</div>
                                            <div style='font-size:13px;font-weight:700;color:#111827;'>{$drNumber}</div>
                                        </div>
                                        <div>
                                            <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;'>DR File</div>
                                            {$drFile}
                                        </div>
                                    </div>
                                </div>";
                        }

                        // ── Note ──
                        $noteHtml = '';
                        if (!blank($note)) {
                            $noteHtml = "
                                <div style='border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-bottom:16px;background:#fafafa;'>
                                    <div style='font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;'>Note</div>
                                    <div style='font-size:13px;color:#374151;'>{$note}</div>
                                </div>";
                        }

                        return new \Illuminate\Support\HtmlString("
                            <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>

                                <!-- Header card -->
                                <div style='background:linear-gradient(135deg,#1e3a5f 0%,#1e40af 100%);
                                    border-radius:12px;padding:18px 20px;margin-bottom:16px;'>
                                    <div style='display:flex;justify-content:space-between;align-items:flex-start;'>
                                        <div>
                                            <div style='font-size:18px;font-weight:800;color:#fff;letter-spacing:-0.02em;'>
                                                {$siteName}
                                            </div>
                                            <div style='font-size:12px;color:#93c5fd;margin-top:4px;'>
                                                PO #{$poNumber}
                                            </div>
                                        </div>
                                        <span style='background:{$statusColor};color:#fff;font-size:11px;font-weight:700;
                                            padding:4px 14px;border-radius:999px;letter-spacing:.04em;white-space:nowrap;'>
                                            {$statusLabel}
                                        </span>
                                    </div>
                                    <div style='display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;'>
                                        <div style='background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>PO Date</div>
                                            <div style='font-size:13px;font-weight:600;color:#fff;'>{$poDate}</div>
                                        </div>
                                        <div style='background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>Status Date</div>
                                            <div style='font-size:13px;font-weight:600;color:#fff;'>{$statusDateFormatted}</div>
                                        </div>
                                        <div style='background:rgba(255,255,255,.08);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>Total Items</div>
                                            <div style='font-size:13px;font-weight:600;color:#fff;'>" . $record->purchaseOrderItems->count() . " line(s)</div>
                                        </div>
                                        <div style='background:rgba(255,255,255,.08);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>DR Number</div>
                                            <div style='font-size:13px;font-weight:600;color:#fff;'>" . ($record->dr_number ?? '—') . "</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary bar -->
                                {$grandTotalBar}

                                <!-- Note -->
                                {$noteHtml}

                                <!-- DR section -->
                                {$drHtml}

                                <!-- Items table -->
                                <div style='border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:4px;'>
                                    <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                        Order Items
                                    </div>
                                    <table style='width:100%;border-collapse:collapse;'>
                                        <thead>
                                            <tr style='background:#1e3a5f;'>
                                                <th style='padding:7px 14px;text-align:left;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Item</th>
                                                <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;width:70px;'>Size</th>
                                                <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#93c5fd;text-transform:uppercase;letter-spacing:.05em;width:60px;'>Qty</th>
                                                <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#86efac;text-transform:uppercase;letter-spacing:.05em;width:75px;'>Released</th>
                                                <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#fcd34d;text-transform:uppercase;letter-spacing:.05em;width:80px;'>Remaining</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$itemRows}</tbody>
                                    </table>
                                </div>

                            </div>
                        ");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // ─── APPROVE ──────────────────────────────────────────────
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SmePurchaseOrder $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (SmePurchaseOrder $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->load('purchaseOrderItems.smeItem', 'purchaseOrderItems.smeItemVariant');

                            // ── PASS 1: validate all stock first ──────────────────────────
                            $plannedDeductions = [];
                            foreach ($record->purchaseOrderItems as $item) {
                                $variantId = $item->sme_item_variant_id;
                                $plannedDeductions[$variantId] = ($plannedDeductions[$variantId] ?? 0) + (int) $item->quantity;
                            }

                            foreach ($plannedDeductions as $variantId => $totalPlanned) {
                                $variant = \App\Models\SmeItemVariants::find($variantId);
                                if (! $variant || $variant->sme_item_quantity < $totalPlanned) {
                                    throw new \Exception(
                                        "Insufficient stock for variant ID {$variantId}. " .
                                        "Available: " . ($variant?->sme_item_quantity ?? 0) . ", Required: {$totalPlanned}"
                                    );
                                }
                            }

                            // ── PASS 2: capture stock_before per variant BEFORE any decrement ──
                            $stockBeforeMap = [];
                            foreach ($plannedDeductions as $variantId => $_) {
                                $variant = \App\Models\SmeItemVariants::find($variantId);
                                $stockBeforeMap[$variantId] = $variant ? (int) $variant->sme_item_quantity : 0;
                            }

                            // ── PASS 3: decrement each variant once ───────────────────────
                            foreach ($plannedDeductions as $variantId => $totalPlanned) {
                                \App\Models\SmeItemVariants::where('id', $variantId)
                                    ->decrement('sme_item_quantity', $totalPlanned);
                            }

                            // ── PASS 4: build note rows with per-item stock windows ────────
                            $noteItems      = [];
                            $variantRunning = [];

                            foreach ($record->purchaseOrderItems as $item) {
                                $qty       = (int) $item->quantity;
                                $variantId = $item->sme_item_variant_id;

                                $totalDeducted   = $plannedDeductions[$variantId] ?? 0;
                                $stockOriginal   = $stockBeforeMap[$variantId] ?? 0;
                                $alreadyConsumed = $variantRunning[$variantId] ?? 0;

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

                            $record->update([
                                'status'      => 'approved',
                                'approved_at' => now(),
                            ]);

                            SmePurchaseOrderLog::create([
                                'sme_purchase_order_id' => $record->id,
                                'user_id'               => Auth::id(),
                                'action'                => 'approved',
                                'status_from'           => 'pending',
                                'status_to'             => 'approved',
                                'note'                  => $noteItems,
                            ]);
                        });
                    }),

                // ─── REJECT ───────────────────────────────────────────────
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SmePurchaseOrder $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (SmePurchaseOrder $record): void {
                        $record->update(['status' => 'rejected']);

                        SmePurchaseOrderLog::create([
                            'sme_purchase_order_id' => $record->id,
                            'user_id'               => Auth::id(),
                            'action'                => 'rejected',
                            'status_from'           => 'pending',
                            'status_to'             => 'rejected',
                            'note'                  => null,
                        ]);
                    }),

                // ─── ATTACH DR ────────────────────────────────────────────
                Action::make('billing')
                    ->label('Billing')
                    ->color('warning')
                    ->icon('heroicon-o-banknotes')
                    ->modalWidth('4xl')
                    ->visible(fn (SmePurchaseOrder $record): bool =>
                            $record->status === 'approved'
                        )
                    ->modalSubmitAction(function ($action, SmePurchaseOrder $record) {
                        if (\App\Models\SmeBilling::where('sme_purchase_order_id', $record->id)->exists()) {
                            return false;
                        }
                        return $action;
                    })
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (SmePurchaseOrder $record) {
                        $record->loadMissing('purchaseOrderItems.smeItem', 'purchaseOrderItems.smeItemVariant', 'site');

                        $existingBillings = \App\Models\SmeBilling::where('sme_purchase_order_id', $record->id)
                            ->latest()
                            ->get();

                        if ($existingBillings->count() > 0) {
                            $rows = '';
                            foreach ($existingBillings as $billing) {
                                $statusColor = $billing->status === 'billed' ? '#16a34a' : '#d97706';
                                $statusLabel = strtoupper($billing->status);
                                $billedTo    = e($billing->billed_to);
                                $total       = number_format($billing->total_price, 2);
                                $date        = \Carbon\Carbon::parse($billing->created_at)->timezone('Asia/Manila')->format('M d, Y');
                                $typeLabel   = $billing->billing_type === 'client' ? 'CLIENT' : 'OTHER';
                                $typeBg      = $billing->billing_type === 'client' ? '#1d4ed8' : '#6b7280';

                                $rows .= "
                                    <tr>
                                        <td style='padding:10px 14px;font-size:12.5px;font-weight:600;color:#111827;border-bottom:1px solid #f1f5f9;'>
                                            {$billedTo}
                                        </td>
                                        <td style='padding:10px 14px;font-size:12px;text-align:center;border-bottom:1px solid #f1f5f9;'>
                                            <span style='background:{$typeBg};color:#fff;font-size:9.5px;font-weight:700;
                                                padding:3px 9px;border-radius:999px;letter-spacing:.04em;'>{$typeLabel}</span>
                                        </td>
                                        <td style='padding:10px 14px;font-size:13px;color:#1d4ed8;font-weight:700;
                                            text-align:right;border-bottom:1px solid #f1f5f9;'>&#x20B1;{$total}</td>
                                        <td style='padding:10px 14px;font-size:11.5px;color:#6b7280;
                                            text-align:center;border-bottom:1px solid #f1f5f9;'>{$date}</td>
                                        <td style='padding:10px 14px;text-align:center;border-bottom:1px solid #f1f5f9;'>
                                            <span style='background:{$statusColor};color:#fff;font-size:9.5px;font-weight:700;
                                                padding:3px 9px;border-radius:999px;letter-spacing:.04em;'>{$statusLabel}</span>
                                        </td>
                                    </tr>";
                            }

                            $grandTotal = number_format($existingBillings->sum('total_price'), 2);

                            return new \Illuminate\Support\HtmlString("
                                <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                                    <div style='font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;
                                        letter-spacing:.08em;margin-bottom:10px;'>
                                        Existing Billings &nbsp;({$existingBillings->count()})
                                    </div>
                                    <div style='border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;
                                        box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:12px;'>
                                        <table style='width:100%;border-collapse:collapse;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:10px 14px;text-align:left;font-size:10.5px;font-weight:600;
                                                        color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Billed To</th>
                                                    <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:600;
                                                        color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Type</th>
                                                    <th style='padding:10px 14px;text-align:right;font-size:10.5px;font-weight:600;
                                                        color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Total</th>
                                                    <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:600;
                                                        color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Date</th>
                                                    <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:600;
                                                        color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$rows}</tbody>
                                            <tfoot>
                                                <tr style='background:#f0f9ff;border-top:2px solid #93c5fd;'>
                                                    <td colspan='2' style='padding:10px 14px;font-size:11.5px;font-weight:600;
                                                        color:#374151;text-align:right;'>Grand Total</td>
                                                    <td style='padding:10px 14px;font-size:15px;font-weight:800;
                                                        color:#1d4ed8;text-align:right;letter-spacing:-0.03em;'>&#x20B1;{$grandTotal}</td>
                                                    <td colspan='2'></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div style='padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;
                                        font-size:12px;color:#854d0e;text-align:center;font-weight:500;'>
                                        &#9432;&nbsp; A billing already exists for this PO. No additional billing can be added.
                                    </div>
                                </div>
                            ");
                        }

                        $siteName = e($record->site?->site_name ?? '—');
                        $poNumber = e($record->po_number ?? '—');
                        $drNumber = e($record->dr_number ?? '—');
                        $status   = strtoupper($record->status);

                        return new \Illuminate\Support\HtmlString("
                            <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                                <div style='display:flex;align-items:center;gap:10px;padding:10px 14px;
                                    background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:12px;'>
                                    <div style='width:8px;height:8px;border-radius:50%;background:#d97706;flex-shrink:0;'></div>
                                    <span style='font-size:13px;font-weight:600;color:#1e3a5f;'>{$siteName}</span>
                                    <span style='color:#d1d5db;'>·</span>
                                    <span style='font-size:12.5px;color:#374151;'>PO #{$poNumber}</span>
                                    <span style='color:#d1d5db;'>·</span>
                                    <span style='font-size:12.5px;color:#374151;'>DR #{$drNumber}</span>
                                    <span style='margin-left:auto;font-size:11px;font-weight:700;color:#1d4ed8;
                                        background:#dbeafe;padding:3px 10px;border-radius:999px;letter-spacing:.03em;'>{$status}</span>
                                </div>
                                <div style='font-size:12px;color:#6b7280;line-height:1.6;'>
                                    Fill in client billing details below. Upload the signed DR document to complete this billing.
                                </div>
                            </div>
                        ");
                    })
                    ->form(function (SmePurchaseOrder $record) {
                        if (\App\Models\SmeBilling::where('sme_purchase_order_id', $record->id)->exists()) {
                            return [];
                        }

                        $record->loadMissing('purchaseOrderItems.smeItem', 'purchaseOrderItems.smeItemVariant', 'site');

                        // ── Build billing items ──
                        $billingItems = [];
                        foreach ($record->purchaseOrderItems as $item) {
                            $qty = (int) $item->quantity;
                            if ($qty <= 0) continue;

                            $billingItems[] = [
                                'item_name'  => $item->smeItem?->sme_item_name        ?? '—',
                                'size'       => $item->smeItemVariant?->sme_item_size  ?? '—',
                                'quantity'   => $qty,
                                'unit_price' => (float) ($item->smeItem?->sme_item_price ?? 0),
                            ];
                        }

                        $billingItemsJson = json_encode($billingItems, JSON_UNESCAPED_UNICODE);

                        $grandTotal = array_sum(array_map(
                            fn ($i) => (float) ($i['unit_price'] ?? 0) * (int) ($i['quantity'] ?? 0),
                            $billingItems
                        ));

                        $defaultBilledTo = $record->site?->client?->client_name
                            ?? $record->site?->site_name
                            ?? '';

                        // ── Items table rows ──
                        $tableRows = '';
                        foreach ($billingItems as $i => $item) {
                            $sub = (float) $item['unit_price'] * (int) $item['quantity'];
                            $bg  = $i % 2 === 0 ? '#ffffff' : '#f8fafc';
                            $tableRows .= "
                                <tr style='background:{$bg};'>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#111827;'>
                                        " . e($item['item_name']) . "
                                    </td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#374151;text-align:center;'>
                                        " . e($item['size']) . "
                                    </td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:12.5px;font-weight:700;color:#1d4ed8;text-align:center;'>
                                        " . (int) $item['quantity'] . "
                                    </td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:12.5px;color:#374151;text-align:right;'>
                                        &#x20B1;" . number_format((float) $item['unit_price'], 2) . "
                                    </td>
                                    <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:12.5px;font-weight:600;color:#111827;text-align:right;'>
                                        &#x20B1;" . number_format($sub, 2) . "
                                    </td>
                                </tr>";
                        }

                        $fields = [];

                        // ── Billed To ──
                        $fields[] = \Filament\Forms\Components\TextInput::make('billed_to')
                            ->label('Billed To (Client)')
                            ->default($defaultBilledTo)
                            ->required()
                            ->columnSpanFull();

                        // ── Info note ──
                        $fields[] = \Filament\Forms\Components\Placeholder::make('billing_note')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString("
                                <div style='font-family:\"DM Sans\",system-ui,sans-serif;padding:11px 15px;
                                    background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;
                                    font-size:12.5px;color:#374151;line-height:1.6;'>
                                    <strong style='color:#1e3a5f;'>Client billing</strong>
                                    — one billing record will be created for the client based on all items in this PO.
                                    <br><span style='color:#7c3aed;font-size:12px;'>
                                        &#9432; A <strong>signed DR</strong> upload is required to complete this billing.
                                    </span>
                                </div>
                            "))
                            ->columnSpanFull();

                        // ── Hidden fields ──
                        $fields[] = \Filament\Forms\Components\Hidden::make('billing_items')->default($billingItemsJson);
                        $fields[] = \Filament\Forms\Components\Hidden::make('status')->default('pending');

                        // ── Items preview card ──
                        $fields[] = \Filament\Forms\Components\Placeholder::make('items_preview')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString("
                                <div style='font-family:\"DM Sans\",system-ui,sans-serif;
                                    border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;
                                    box-shadow:0 1px 4px rgba(0,0,0,.05);'>

                                    <!-- Card header -->
                                    <div style='display:flex;align-items:center;justify-content:space-between;
                                        padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>
                                        <span style='font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;'>
                                            " . count($billingItems) . " item(s)
                                        </span>
                                        <span style='font-size:14px;font-weight:700;color:#1d4ed8;letter-spacing:-0.03em;'>
                                            &#x20B1;" . number_format($grandTotal, 2) . "
                                            &nbsp;<span style='font-size:11px;font-weight:500;color:#6b7280;'>total</span>
                                        </span>
                                    </div>

                                    <!-- Items table -->
                                    <div style='overflow-x:auto;'>
                                        <table style='width:100%;border-collapse:collapse;min-width:400px;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:8px 14px;text-align:left;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Item</th>
                                                    <th style='padding:8px 14px;text-align:center;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;width:70px;'>Size</th>
                                                    <th style='padding:8px 14px;text-align:center;font-size:10px;font-weight:700;color:#93c5fd;text-transform:uppercase;letter-spacing:.05em;width:60px;'>Qty</th>
                                                    <th style='padding:8px 14px;text-align:right;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;width:110px;'>Unit Price</th>
                                                    <th style='padding:8px 14px;text-align:right;font-size:10px;font-weight:700;color:#fcd34d;text-transform:uppercase;letter-spacing:.05em;width:100px;'>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$tableRows}</tbody>
                                            <tfoot>
                                                <tr style='background:#eff6ff;border-top:2px solid #93c5fd;'>
                                                    <td colspan='4' style='padding:9px 14px;font-size:12px;font-weight:600;
                                                        color:#374151;text-align:right;'>Grand Total</td>
                                                    <td style='padding:9px 14px;font-size:14px;font-weight:800;
                                                        color:#1d4ed8;text-align:right;'>&#x20B1;" . number_format($grandTotal, 2) . "</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <!-- DR upload label section -->
                                    <div style='padding:12px 16px 4px;border-top:1px solid #ede9fe;background:#faf5ff;'>
                                        <div style='display:flex;align-items:center;gap:8px;margin-bottom:6px;'>
                                            <span style='width:7px;height:7px;border-radius:50%;background:#7c3aed;flex-shrink:0;display:inline-block;'></span>
                                            <span style='font-size:11.5px;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.06em;'>
                                                Signed DR Required
                                            </span>
                                            <span style='font-size:10.5px;color:#9ca3af;font-weight:400;'>(required)</span>
                                        </div>
                                        <div style='font-size:11.5px;color:#6b7280;line-height:1.5;padding-left:15px;'>
                                            Upload the signed Delivery Receipt for this Purchase Order.
                                        </div>
                                    </div>
                                </div>
                            "))
                            ->columnSpanFull();

                        // ── DR upload fields — flush below the card ──
                        $fields[] = \Filament\Forms\Components\TextInput::make('dr_number')
                            ->label('DR Number')
                            ->placeholder('e.g. DR-2024-001')
                            ->required()
                            ->extraAttributes([
                                'style' => 'background:#faf5ff;border:1px solid #e9d5ff;border-top:0;
                                    border-radius:0;padding:14px 16px;margin-top:-2px;',
                            ]);

                        $fields[] = \Filament\Forms\Components\FileUpload::make('dr_image')
                            ->label('Signed DR Image')
                            ->helperText('Upload the signed Delivery Receipt for this PO')
                            ->image()
                            ->imagePreviewHeight('130')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->directory('billing-dr')
                            ->required()
                            ->columnSpanFull()
                            ->extraAttributes([
                                'style' => 'background:#faf5ff;border:1px solid #e9d5ff;border-top:0;
                                    border-radius:0;padding:14px 16px;margin-top:-2px;',
                            ]);

                        $fields[] = \Filament\Forms\Components\DatePicker::make('dr_date_signed')
                            ->label('Date Signed')
                            ->default(now()->toDateString())
                            ->required()
                            ->extraAttributes([
                                'style' => 'background:#faf5ff;',
                            ]);

                        $fields[] = \Filament\Forms\Components\TextInput::make('dr_remarks')
                            ->label('Remarks')
                            ->placeholder('Optional notes')
                            ->extraAttributes([
                                'style' => 'background:#faf5ff;border:1px solid #e9d5ff;border-top:0;
                                    border-radius:0 0 12px 12px;padding:14px 16px;margin-top:-2px;',
                            ]);

                        return $fields;
                    })
                    ->action(function (SmePurchaseOrder $record, array $data, Action $action): void {
                        if (\App\Models\SmeBilling::where('sme_purchase_order_id', $record->id)->exists()) {
                            return;
                        }

                        $record->loadMissing('purchaseOrderItems.smeItem', 'purchaseOrderItems.smeItemVariant', 'site');

                        $billingItems = json_decode($data['billing_items'] ?? '[]', true);

                        if (!is_array($billingItems) || empty($billingItems)) {
                            \Filament\Notifications\Notification::make()
                                ->title('No billing items')
                                ->body('Could not read billing items. Please try again.')
                                ->warning()
                                ->send();
                            $action->halt();
                            return;
                        }

                        $total = array_sum(
                            array_map(
                                fn ($i) => (float) ($i['unit_price'] ?? 0) * (int) ($i['quantity'] ?? 0),
                                $billingItems
                            )
                        );

                        $billing = \App\Models\SmeBilling::create([
                            'sme_purchase_order_id' => $record->id,
                            'billed_to'             => $data['billed_to'] ?? $record->site?->site_name ?? '—',
                            'billing_type'          => 'client',
                            'billing_items'         => $billingItems,
                            'total_price'           => $total,
                            'status'                => 'pending',
                            'billed_at'             => null,
                            'created_by'            => \Illuminate\Support\Facades\Auth::id(),
                        ]);

                        \App\Models\BillingDr::create([
                            'billable_id'     => $billing->id,
                            'billable_type'   => \App\Models\SmeBilling::class,
                            'sourceable_id'   => $record->id,
                            'sourceable_type' => \App\Models\SmePurchaseOrder::class,
                            'employee_name'   => null,
                            'dr_number'       => $data['dr_number']      ?? '—',
                            'date_signed'     => $data['dr_date_signed'] ?? null,
                            'dr_image'        => $data['dr_image']        ?? null,
                            'remarks'         => $data['dr_remarks']      ?? null,
                            'uploaded_by'     => \Illuminate\Support\Facades\Auth::id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Billing Created')
                            ->body('Client billing of ₱' . number_format($total, 2) . ' saved successfully.')
                            ->success()
                            ->send();
                    }),

                // ─── LOGS ─────────────────────────────────────────────────
                Action::make('view_logs')
                    ->label('Logs')
                    ->color('gray')
                    ->icon('heroicon-o-clock')
                    ->modalWidth('2xl')
                    ->modalHeading('Purchase Order Logs')
                    ->modalContent(function (SmePurchaseOrder $record) {
                        $logs = SmePurchaseOrderLog::where('sme_purchase_order_id', $record->id)
                            ->with('user')
                            ->latest()
                            ->get();

                        if ($logs->isEmpty()) {
                            return new \Illuminate\Support\HtmlString("
                                <div style='text-align:center;padding:32px;color:#9ca3af;font-size:13px;'>
                                    No logs found for this purchase order.
                                </div>
                            ");
                        }

                        $rows = $logs->map(function ($log) {
                            $user   = e($log->user?->name ?? 'System');
                            $date   = \Carbon\Carbon::parse($log->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A');
                            $from   = e($log->status_from ?? '—');
                            $to     = e($log->status_to ?? '—');
                            $action = strtoupper($log->action);

                            $badgeColor = match ($log->action) {
                                'approved'  => '#16a34a',
                                'rejected'  => '#dc2626',
                                'attach_dr' => '#0d9488',
                                'created'   => '#7c3aed',
                                default     => '#6b7280',
                            };

                            // ── Items / stock detail ──
                            $itemsHtml = '';
                            $noteData  = is_array($log->note) ? $log->note : [];

                            if (!empty($noteData)) {
                                if ($log->action === 'approved') {
                                    $itemsHtml = "
                                        <div style='margin-top:6px;padding:6px 8px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;'>
                                            <div style='font-size:10px;font-weight:700;color:#166534;margin-bottom:4px;'>STOCK DEDUCTED:</div>";
                                    foreach ($noteData as $row) {
                                        $label  = e($row['label'] ?? '—');
                                        $qty    = (int) ($row['qty'] ?? 0);
                                        $before = $row['stock_before'] ?? null;
                                        $after  = $row['stock_after']  ?? null;

                                        $stockHtml = '';
                                        if ($before !== null && $after !== null) {
                                            $afterColor = $after >= 0 ? '#16a34a' : '#dc2626';
                                            $stockHtml = "<span style='font-size:10px;color:#9ca3af;margin-left:6px;'>
                                                stock: <strong style='color:#374151;'>{$before}</strong>
                                                → <strong style='color:{$afterColor};'>{$after}</strong>
                                            </span>";
                                        }

                                        $itemsHtml .= "
                                            <div style='font-size:11px;color:#374151;padding:3px 0;
                                                border-bottom:1px dashed #d1fae5;display:flex;justify-content:space-between;align-items:center;'>
                                                <span>{$label}{$stockHtml}</span>
                                                <span style='font-weight:700;color:#16a34a;'>×{$qty}</span>
                                            </div>";
                                    }
                                    $itemsHtml .= "</div>";

                                } elseif ($log->action === 'attach_dr') {
                                    $label = e($noteData[0]['label'] ?? '—');
                                    $itemsHtml = "
                                        <div style='margin-top:4px;font-size:11px;color:#0d9488;font-style:italic;'>{$label}</div>";
                                }
                            }

                            return "
                                <tr>
                                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                        <div style='font-size:11px;color:#374151;white-space:nowrap;'>{$date}</div>
                                        <div style='font-size:10px;color:#9ca3af;margin-top:2px;'>{$user}</div>
                                    </td>
                                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                        <span style='background:{$badgeColor};color:#fff;font-size:10px;font-weight:700;
                                            padding:2px 8px;border-radius:999px;'>{$action}</span>
                                    </td>
                                    <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                        <div style='font-size:11px;color:#374151;'>{$from} → {$to}</div>
                                        {$itemsHtml}
                                    </td>
                                </tr>";
                        })->implode('');

                        return new \Illuminate\Support\HtmlString("
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

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}