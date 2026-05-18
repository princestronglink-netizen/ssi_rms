<?php

namespace App\Filament\Resources\SmePurchaseOrders\Schemas;

use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use App\Models\SmeItems;
use App\Models\SmeItemVariants;
use Illuminate\Support\HtmlString;

class SmePurchaseOrderForm
{
    /**
     * Check if any repeater item exceeds available stock.
     * Shared by both the banner Placeholder and the Toggle visibility closure.
     */
    private static function hasStockIssues(Get $get): bool
    {
        if ($get('status') !== 'pending') return false;

        $aggregate = [];
        foreach ($get('purchaseOrderItems') ?? [] as $item) {
            $variantId = $item['sme_item_variant_id'] ?? null;
            $qty       = (int) ($item['quantity'] ?? 0);
            if (!$variantId || $qty <= 0) continue;
            $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
        }

        foreach ($aggregate as $variantId => $total) {
            $variant = SmeItemVariants::find($variantId);
            if ($variant && $total > (int) $variant->sme_item_quantity) {
                return true;
            }
        }

        return false;
    }

    private static function hasStockIssuesForApproved(Get $get): bool
    {
        if ($get('status') !== 'approved') return false;

        $aggregate = [];
        foreach ($get('purchaseOrderItems') ?? [] as $item) {
            $variantId = $item['sme_item_variant_id'] ?? null;
            $qty       = (int) ($item['quantity'] ?? 0);
            if (!$variantId || $qty <= 0) continue;
            $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
        }

        foreach ($aggregate as $variantId => $total) {
            $variant = SmeItemVariants::find($variantId);
            if ($variant && $total > (int) $variant->sme_item_quantity) {
                return true;
            }
        }

        return false;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([

                // ── Site ──────────────────────────────────────────────────────────
                Select::make('site_id')
                    ->label('Site')
                    ->relationship('site', 'site_name')
                    ->searchable()
                    ->preload()
                    ->required(),

                // ── Status ────────────────────────────────────────────────────────
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'Pending',
                        'approved' => 'Approved',
                    ])
                    ->default('pending')
                    ->required()
                    ->live(),

                // ── PO Fields ─────────────────────────────────────────────────────
                TextInput::make('po_number')
                    ->label('PO Number')
                    ->unique(ignoreRecord: true),

                DatePicker::make('po_date')
                    ->label('PO Date'),

                FileUpload::make('po_file_path')
                    ->label('PO File')
                    ->directory('purchase-orders/po')
                    ->columnSpanFull()
                    ->acceptedFileTypes(['application/pdf', 'image/*']),

                Textarea::make('note')
                    ->label('Note')
                    ->columnSpanFull(),

                // ── Order Items Repeater ───────────────────────────────────────────
                // NO ->relationship() — data must flow raw through $data in the Action.
                // With ->relationship() Filament strips items before the action closure
                // runs (no parent record exists yet on a ListRecords modal).
                Repeater::make('purchaseOrderItems')
                    ->label('Order Items')
                    ->columnSpanFull()
                    ->live()
                    ->schema([
                        Select::make('sme_item_id')
                            ->label('Item')
                            ->options(
                                fn () => SmeItems::orderBy('sme_item_name')
                                    ->pluck('sme_item_name', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('sme_item_variant_id', null)),

                        Select::make('sme_item_variant_id')
                            ->label('Variant / Size')
                            ->options(function (Get $get): array {
                                $itemId = $get('sme_item_id');
                                if (!$itemId) return [];

                                return SmeItemVariants::where('sme_item_id', $itemId)
                                    ->orderBy('sme_item_size')
                                    ->pluck('sme_item_size', 'id')
                                    ->toArray();
                            })
                            ->placeholder(fn (Get $get): string => $get('sme_item_id')
                                ? 'Select a variant...'
                                : 'Select an item first'
                            )
                            ->searchable()
                            ->disabled(fn (Get $get): bool => !$get('sme_item_id'))
                            ->dehydrated()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('quantity', null)),

                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                $released = (int) $get('released_quantity');
                                $set('remaining_quantity', max(0, (int) $state - $released));
                            }),

                        TextInput::make('released_quantity')
                            ->label('Released Quantity')
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->hidden()
                            ->dehydrated(),

                        TextInput::make('remaining_quantity')
                            ->label('Remaining Quantity')
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->disabled()
                            ->hidden()
                            ->dehydrated(),

                        // Per-row stock indicator
                        Placeholder::make('stock_summary')
                            ->label('')
                            ->columnSpanFull()
                            ->content(function (Get $get): HtmlString {
                                $variantId = $get('sme_item_variant_id');
                                $qty       = (int) ($get('quantity') ?? 0);

                                if (!$variantId) {
                                    return new HtmlString(
                                        '<span style="color:#9ca3af;font-size:12px;">Select a variant to see stock.</span>'
                                    );
                                }

                                $variant = SmeItemVariants::find($variantId);
                                if (!$variant) {
                                    return new HtmlString(
                                        '<span style="color:#dc2626;font-size:12px;">Variant not found.</span>'
                                    );
                                }

                                $stock     = (int) $variant->sme_item_quantity;
                                $remaining = $stock - $qty;
                                $color     = $remaining < 0 ? '#dc2626' : ($remaining === 0 ? '#d97706' : '#16a34a');
                                $icon      = $remaining < 0 ? '⛔' : ($remaining === 0 ? '⚠️' : '✅');
                                $label     = $remaining < 0
                                    ? 'Over by ' . abs($remaining)
                                    : ($remaining === 0 ? 'Exact stock' : "{$remaining} remaining after order");

                                return new HtmlString("
                                    <div style='font-size:12px;'>
                                        <span style='color:#374151;'>In stock: <strong>{$stock}</strong></span>
                                        &nbsp;|&nbsp;
                                        <span style='color:{$color};font-weight:600;'>{$icon} {$label}</span>
                                    </div>
                                ");
                            }),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->addActionLabel('Add item'),

                // ── Order Summary ─────────────────────────────────────────────────
                Placeholder::make('purchase_order_summary')
                    ->label('Order Summary')
                    ->columnSpanFull()
                    ->content(function (Get $get): HtmlString {
                        $items = $get('purchaseOrderItems') ?? [];

                        if (empty($items)) {
                            return new HtmlString('<span style="color:#9ca3af;">No items added yet.</span>');
                        }

                        $aggregated = [];
                        $grandTotal = 0;

                        foreach ($items as $item) {
                            $itemId    = $item['sme_item_id'] ?? null;
                            $variantId = $item['sme_item_variant_id'] ?? null;
                            $qty       = (int) ($item['quantity'] ?? 0);

                            if (!$itemId || !$variantId || $qty <= 0) continue;

                            $grandTotal += $qty;
                            $key = $itemId . '_' . $variantId;

                            if (!isset($aggregated[$key])) {
                                $itemModel    = SmeItems::find($itemId);
                                $variantModel = SmeItemVariants::find($variantId);

                                $aggregated[$key] = [
                                    'item_name'    => $itemModel?->sme_item_name    ?? '—',
                                    'variant_name' => $variantModel?->sme_item_size ?? '—',
                                    'stock'        => (int) ($variantModel?->sme_item_quantity ?? 0),
                                    'total_qty'    => 0,
                                ];
                            }

                            $aggregated[$key]['total_qty'] += $qty;
                        }

                        if (empty($aggregated)) {
                            return new HtmlString('<span style="color:#9ca3af;">No valid items added yet.</span>');
                        }

                        $rows   = '';
                        $rowNum = 1;

                        foreach ($aggregated as $agg) {
                            $remaining   = $agg['stock'] - $agg['total_qty'];
                            $isOver      = $remaining < 0;
                            $isExact     = $remaining === 0;
                            $remainColor = $isOver ? '#dc2626' : ($isExact ? '#d97706' : '#16a34a');
                            $statusIcon  = $isOver ? '⛔' : ($isExact ? '⚠️' : '✅');
                            $remainLabel = $isOver
                                ? 'Over by ' . abs($remaining)
                                : ($isExact ? 'Exact' : $remaining . ' left');

                            $rows .= "
                                <tr>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:#6b7280;'>{$rowNum}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;'>{$agg['item_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$agg['variant_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:700;color:#1d4ed8;'>{$agg['total_qty']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:#374151;'>{$agg['stock']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:{$remainColor};font-weight:600;'>{$statusIcon} {$remainLabel}</td>
                                </tr>";
                            $rowNum++;
                        }

                        return new HtmlString("
                            <table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
                                <thead>
                                    <tr style='background:#1e3a5f;'>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;width:40px;'>#</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:left;'>Item</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Variant / Size</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Total Ordered</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>In Stock</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Remaining Stock</th>
                                    </tr>
                                </thead>
                                <tbody>{$rows}</tbody>
                                <tfoot>
                                    <tr style='background:#f8fafc;'>
                                        <td colspan='3' style='padding:5px 8px;font-size:11px;font-weight:700;color:#374151;'>Grand Total</td>
                                        <td style='padding:5px 8px;font-size:12px;font-weight:900;color:#1d4ed8;text-align:center;'>{$grandTotal}</td>
                                        <td colspan='2'></td>
                                    </tr>
                                </tfoot>
                            </table>
                        ");
                    }),

                // ── Stock Warning Banner ───────────────────────────────────────────
                // Visible only when status=pending AND items exceed stock.
                // Driven by the Repeater's ->live() so it reacts as items change.
                // No ->live() on itself — it's a read-only display.
                Placeholder::make('stock_warning_banner')
                    ->label('')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => self::hasStockIssues($get))
                    ->content(function (Get $get): HtmlString {
                        $aggregate = [];
                        foreach ($get('purchaseOrderItems') ?? [] as $item) {
                            $variantId = $item['sme_item_variant_id'] ?? null;
                            $qty       = (int) ($item['quantity'] ?? 0);
                            if (!$variantId || $qty <= 0) continue;
                            $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
                        }

                        $rows = '';
                        foreach ($aggregate as $variantId => $totalOrdered) {
                            $variant = SmeItemVariants::find($variantId);
                            if (!$variant) continue;
                            $stock = (int) $variant->sme_item_quantity;
                            if ($totalOrdered <= $stock) continue;

                            $shortfall = $totalOrdered - $stock;
                            $size      = e($variant->sme_item_size);
                            $rows .= "
                                <tr>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fde68a;'><strong>{$size}</strong></td>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fde68a;text-align:center;color:#1d4ed8;font-weight:700;'>{$totalOrdered}</td>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fde68a;text-align:center;'>{$stock}</td>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fde68a;text-align:center;color:#dc2626;font-weight:700;'>-{$shortfall}</td>
                                </tr>";
                        }

                        if (!$rows) return new HtmlString('');

                        return new HtmlString("
                            <div style='border:2px solid #f59e0b;border-radius:8px;overflow:hidden;background:#fffbeb;'>
                                <div style='background:#f59e0b;padding:10px 14px;display:flex;align-items:center;gap:8px;'>
                                    <span style='font-size:18px;'>⚠️</span>
                                    <span style='font-size:13px;font-weight:700;color:#fff;'>
                                        Stock Warning — Quantities Exceed Available Stock
                                    </span>
                                </div>
                                <div style='padding:12px 14px;'>
                                    <table style='width:100%;border-collapse:collapse;margin-bottom:10px;'>
                                        <thead>
                                            <tr style='background:#d97706;'>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:left;'>Variant</th>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:center;'>Ordered</th>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:center;'>In Stock</th>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:center;'>Shortfall</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                    <div style='font-size:12px;color:#92400e;line-height:1.6;'>
                                        This order will be saved as <strong>Pending</strong> and
                                        <strong>cannot be approved</strong> until stock is replenished
                                        or quantities are reduced.<br>
                                        <strong>Toggle the switch below to confirm and save anyway.</strong>
                                    </div>
                                </div>
                            </div>
                        ");
                    }),

                // ── Stock Warning Toggle ───────────────────────────────────────────
                // Only appears when there are stock issues on a pending order.
                // NO ->live() here — live() on this field causes Livewire to
                // re-render the component on every toggle click, which resets
                // the visual state of the toggle before Alpine.js can update it.
                // The value is still submitted correctly with the form data.
                Toggle::make('stock_warning_acknowledged')
                    ->label('I understand the stock shortage — save this order as Pending anyway')
                    ->columnSpanFull()
                    ->default(false)
                    ->onColor('warning')
                    ->offColor('gray')
                    ->visible(fn (Get $get): bool => self::hasStockIssues($get)),

                // ── Approved Stock Error Banner ───────────────────────────────────────────
                // Visible only when status=approved AND items exceed stock.
                // Blocks saving — no toggle, no override possible.
                Placeholder::make('approved_stock_error_banner')
                    ->label('')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => self::hasStockIssuesForApproved($get))
                    ->content(function (Get $get): HtmlString {
                        $aggregate = [];
                        foreach ($get('purchaseOrderItems') ?? [] as $item) {
                            $variantId = $item['sme_item_variant_id'] ?? null;
                            $qty       = (int) ($item['quantity'] ?? 0);
                            if (!$variantId || $qty <= 0) continue;
                            $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
                        }

                        $rows = '';
                        foreach ($aggregate as $variantId => $totalOrdered) {
                            $variant = SmeItemVariants::find($variantId);
                            if (!$variant) continue;
                            $stock = (int) $variant->sme_item_quantity;
                            if ($totalOrdered <= $stock) continue;

                            $shortfall = $totalOrdered - $stock;
                            $size      = e($variant->sme_item_size);
                            $rows .= "
                                <tr>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fecaca;'><strong>{$size}</strong></td>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fecaca;text-align:center;color:#1d4ed8;font-weight:700;'>{$totalOrdered}</td>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fecaca;text-align:center;'>{$stock}</td>
                                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid #fecaca;text-align:center;color:#dc2626;font-weight:700;'>-{$shortfall}</td>
                                </tr>";
                        }

                        if (!$rows) return new HtmlString('');

                        return new HtmlString("
                            <div style='border:2px solid #dc2626;border-radius:8px;overflow:hidden;background:#fef2f2;'>
                                <div style='background:#dc2626;padding:10px 14px;display:flex;align-items:center;gap:8px;'>
                                    <span style='font-size:18px;'>⛔</span>
                                    <span style='font-size:13px;font-weight:700;color:#fff;'>
                                        Cannot Approve — Quantities Exceed Available Stock
                                    </span>
                                </div>
                                <div style='padding:12px 14px;'>
                                    <table style='width:100%;border-collapse:collapse;margin-bottom:10px;'>
                                        <thead>
                                            <tr style='background:#b91c1c;'>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:left;'>Variant</th>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:center;'>Ordered</th>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:center;'>In Stock</th>
                                                <th style='padding:5px 10px;font-size:11px;color:#fff;text-align:center;'>Shortfall</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                    <div style='font-size:12px;color:#7f1d1d;line-height:1.6;'>
                                        This order <strong>cannot be saved as Approved</strong> while stock is insufficient.<br>
                                        Please <strong>reduce the quantities</strong>, <strong>replenish stock</strong>,
                                        or change the status to <strong>Pending</strong> to save without deducting stock.
                                    </div>
                                </div>
                            </div>
                        ");
                    }),
            ]);
    }
}