<?php

namespace App\Filament\Resources\UniformIssuances\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\UniformItemVariants;
use App\Models\UniformItems;
use App\Models\UniformSets;
use App\Models\UniformSetItems;
use Illuminate\Support\HtmlString;

class UniformIssuancesForm
{
    // ─────────────────────────────────────────────────────────────────────────
    // Stock helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Aggregate total quantity ordered per variant across ALL recipients
     * and ALL their items. Used by both the form banners and the page action.
     */
    private static function aggregateStock(Get $get): array
    {
        $aggregate = [];

        foreach ($get('uniformIssuanceRecipient') ?? [] as $recipient) {
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
     * True when status = pending AND at least one variant is over stock.
     * Drives the yellow warning banner + toggle visibility.
     */
    private static function hasStockIssues(Get $get): bool
    {
        if ($get('uniform_issuance_status') !== 'pending') return false;

        foreach (self::aggregateStock($get) as $variantId => $total) {
            $variant = UniformItemVariants::find($variantId);
            if ($variant && $total > (int) $variant->uniform_item_quantity) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when status = issued AND at least one variant is over stock.
     * Drives the red error banner visibility.
     */
    private static function hasStockIssuesForIssued(Get $get): bool
    {
        if ($get('uniform_issuance_status') !== 'issued') return false;

        foreach (self::aggregateStock($get) as $variantId => $total) {
            $variant = UniformItemVariants::find($variantId);
            if ($variant && $total > (int) $variant->uniform_item_quantity) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the <tbody> rows shared by both warning banners.
     * $borderColor: '#fde68a' for yellow, '#fecaca' for red.
     */
    private static function buildWarningRows(Get $get, string $borderColor): string
    {
        $rows = '';

        foreach (self::aggregateStock($get) as $variantId => $totalOrdered) {
            $variant = UniformItemVariants::find($variantId);
            if (!$variant) continue;

            $stock = (int) $variant->uniform_item_quantity;
            if ($totalOrdered <= $stock) continue;

            $shortfall = $totalOrdered - $stock;
            $size      = e($variant->uniform_item_size);

            $rows .= "
                <tr>
                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid {$borderColor};'>
                        <strong>{$size}</strong>
                    </td>
                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid {$borderColor};text-align:center;color:#1d4ed8;font-weight:700;'>
                        {$totalOrdered}
                    </td>
                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid {$borderColor};text-align:center;'>
                        {$stock}
                    </td>
                    <td style='padding:5px 10px;font-size:12px;border-bottom:1px solid {$borderColor};text-align:center;color:#dc2626;font-weight:700;'>
                        -{$shortfall}
                    </td>
                </tr>";
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Form
    // ─────────────────────────────────────────────────────────────────────────

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Site ──────────────────────────────────────────────────────
                Select::make('site_id')
                    ->required()
                    ->relationship('site', 'site_name')
                    ->searchable()
                    ->preload(),

                // ── Status ────────────────────────────────────────────────────
                Select::make('uniform_issuance_status')
                    ->options(['pending' => 'Pending', 'issued' => 'Issued'])
                    ->required()
                    ->live()
                    ->default('pending')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state === 'pending') {
                            $set('pending_at', now()->toDateString());
                            $set('issued_at', null);
                        }
                        if ($state === 'issued') {
                            $set('issued_at', now()->toDateString());
                            $set('pending_at', null);
                        }
                    }),

                // ── Dates ─────────────────────────────────────────────────────
                DatePicker::make('pending_at')
                    ->default(now()->toDateString())
                    ->live()
                    ->visible(fn ($get) => $get('uniform_issuance_status') === 'pending'),

                DatePicker::make('issued_at')
                    ->live()
                    ->visible(fn ($get) => $get('uniform_issuance_status') === 'issued'),

                // ── Notes ─────────────────────────────────────────────────────
                Textarea::make('notes')
                    ->columnSpanFull(),

                // ── Recipients Repeater ───────────────────────────────────────
                Repeater::make('uniformIssuanceRecipient')
                    ->relationship('uniformIssuanceRecipient')
                    ->schema([
                        TextInput::make('transaction_id')
                            ->hidden()
                            ->placeholder('Auto-generated'),

                        TextInput::make('employee_name')
                            ->required(),

                        Select::make('employee_status')
                            ->options(['reliever' => 'Reliever', 'posted' => 'Posted'])
                            ->required(),

                        Select::make('position_id')
                            ->relationship('position', 'position_name')
                            ->required()
                            ->preload()
                            ->searchable(),

                        Select::make('uniform_set_id')
                            ->label('Uniform Set')
                            ->options(function () {
                                $sets = UniformSets::pluck('uniform_set_name', 'id')->toArray();
                                return ['manual' => 'Manual (No Set)'] + $sets;
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if (!$state || $state === 'manual') {
                                    $set('uniform_set_id', null);
                                    return;
                                }

                                $setItems = UniformSetItems::where('uniform_set_id', $state)
                                    ->get()
                                    ->map(fn ($item) => [
                                        'uniform_item_id'          => $item->uniform_item_id,
                                        'uniform_item_variant_id'  => null,
                                        'uniform_issuance_type_id' => null,
                                        'quantity'                 => $item->quantity,
                                    ])
                                    ->toArray();

                                $set('uniformIssuanceItem', $setItems);
                            }),

                        // ── Items per Recipient ───────────────────────────────
                        Repeater::make('uniformIssuanceItem')
                            ->relationship('uniformIssuanceItem')
                            ->schema([
                                Select::make('uniform_issuance_type_id')
                                    ->label('Issuance Type')
                                    ->relationship('uniformIssuanceType', 'uniform_issuance_type_name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(3),

                                Select::make('uniform_item_id')
                                    ->options(UniformItems::pluck('uniform_item_name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('uniform_item_variant_id', null)),

                                Select::make('uniform_item_variant_id')
                                    ->options(function (callable $get) {
                                        $itemId = $get('uniform_item_id');
                                        if (!$itemId) return [];
                                        return UniformItemVariants::where('uniform_item_id', $itemId)
                                            ->pluck('uniform_item_size', 'id');
                                    })
                                    ->required()
                                    ->searchable()
                                    ->reactive()
                                    ->hint(function (callable $get) {
                                        $variantId = $get('uniform_item_variant_id');
                                        if (!$variantId) return null;
                                        $variant = UniformItemVariants::find($variantId);
                                        if (!$variant) return null;
                                        $stock = (int) $variant->uniform_item_quantity;
                                        return "Stock: {$stock}";
                                    })
                                    ->hintColor(function (callable $get) {
                                        $variantId = $get('uniform_item_variant_id');
                                        if (!$variantId) return null;
                                        $variant = UniformItemVariants::find($variantId);
                                        if (!$variant) return null;
                                        $stock = (int) $variant->uniform_item_quantity;
                                        return $stock > 0 ? 'success' : 'danger';
                                    }),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->live()
                                    ->rules([
                                        function (callable $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $variantId = $get('uniform_item_variant_id');
                                                if (!$variantId) return;
                                                $variant = UniformItemVariants::find($variantId);
                                                if (!$variant) return;
                                                $stock = (int) $variant->uniform_item_quantity;
                                                $qty   = (int) $value;
                                                if ($qty > $stock) {
                                                    $fail("Quantity ({$qty}) exceeds available stock ({$stock}).");
                                                }
                                            };
                                        }
                                    ]),

                                TextInput::make('released_quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->hidden()
                                    ->dehydrated(),

                                TextInput::make('remaining_quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->hidden()
                                    ->dehydrated(),

                                // Per-row stock indicator
                                Placeholder::make('stock_summary')
                                    ->content(function (callable $get) {
                                        $variantId = $get('uniform_item_variant_id');
                                        $qty       = (int) ($get('quantity') ?? 0);

                                        if (!$variantId) {
                                            return new HtmlString(
                                                '<span style="color:#9ca3af;">Select a variant to see stock.</span>'
                                            );
                                        }

                                        $variant = UniformItemVariants::find($variantId);
                                        if (!$variant) {
                                            return new HtmlString(
                                                '<span style="color:#dc2626;">Variant not found.</span>'
                                            );
                                        }

                                        $stock     = (int) $variant->uniform_item_quantity;
                                        $remaining = $stock - $qty;
                                        $color     = $remaining < 0 ? '#dc2626' : ($remaining === 0 ? '#d97706' : '#16a34a');
                                        $status    = $remaining < 0
                                            ? '⛔ Over by ' . abs($remaining)
                                            : ($remaining === 0
                                                ? '⚠️ Exact stock'
                                                : "✅ {$remaining} remaining after issuance");

                                        return new HtmlString("
                                            <div style='font-size:12px;'>
                                                <span style='color:#374151;'>In stock: <strong>{$stock}</strong></span>
                                                &nbsp;|&nbsp;
                                                <span style='color:{$color};font-weight:600;'>{$status}</span>
                                            </div>
                                        ");
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->columnSpan('full'),
                    ])
                    ->columns(4)
                    ->columnSpan('full')
                    ->live(),

                // ── Global Summary ────────────────────────────────────────────
                Placeholder::make('global_issuance_summary')
                    ->label('Recipient Item Summary')
                    ->content(function (callable $get) {
                        $recipients = $get('uniformIssuanceRecipient') ?? [];

                        if (empty($recipients)) {
                            return new HtmlString('<span style="color:#9ca3af;">No recipients added yet.</span>');
                        }

                        $aggregated = [];
                        $grandTotal = 0;

                        foreach ($recipients as $recipient) {
                            $items = $recipient['uniformIssuanceItem'] ?? [];

                            foreach ($items as $item) {
                                $variantId = $item['uniform_item_variant_id'] ?? null;
                                $itemId    = $item['uniform_item_id'] ?? null;
                                $typeId    = $item['uniform_issuance_type_id'] ?? null;
                                $qty       = (int) ($item['quantity'] ?? 0);

                                if (!$itemId || !$variantId || $qty <= 0) continue;

                                $grandTotal += $qty;
                                $key = $itemId . '_' . $variantId . '_' . ($typeId ?? 'null');

                                if (!isset($aggregated[$key])) {
                                    $itemModel    = UniformItems::find($itemId);
                                    $variantModel = UniformItemVariants::find($variantId);
                                    $typeModel    = $typeId ? \App\Models\UniformIssuanceType::find($typeId) : null;
                                    $stock        = (int) ($variantModel?->uniform_item_quantity ?? 0);

                                    $aggregated[$key] = [
                                        'item_name'    => $itemModel?->uniform_item_name    ?? '—',
                                        'variant_name' => $variantModel?->uniform_item_size ?? '—',
                                        'type_name'    => $typeModel?->uniform_issuance_type_name ?? '—',
                                        'stock'        => $stock,
                                        'total_qty'    => 0,
                                    ];
                                }

                                $aggregated[$key]['total_qty'] += $qty;
                            }
                        }

                        if (empty($aggregated)) {
                            return new HtmlString('<span style="color:#9ca3af;">No items added yet.</span>');
                        }

                        $rows = '';
                        foreach ($aggregated as $data) {
                            $remaining   = $data['stock'] - $data['total_qty'];
                            $isOver      = $remaining < 0;
                            $isExact     = $remaining === 0;
                            $remainColor = $isOver  ? '#dc2626' : ($isExact ? '#d97706' : '#16a34a');
                            $statusIcon  = $isOver  ? '⛔' : ($isExact ? '⚠️' : '✅');
                            $remainLabel = $isOver
                                ? 'Over by ' . abs($remaining)
                                : ($isExact ? 'Exact' : $remaining . ' left');

                            $rows .= "
                                <tr>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;'>{$data['item_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$data['variant_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:#7c3aed;'>{$data['type_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:700;color:#1d4ed8;'>{$data['total_qty']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:#374151;'>{$data['stock']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:{$remainColor};font-weight:600;'>{$statusIcon} {$remainLabel}</td>
                                </tr>";
                        }

                        return new HtmlString("
                            <table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
                                <thead>
                                    <tr style='background:#1e3a5f;'>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:left;'>Item</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Size</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Type</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Total Issued</th>
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
                    })
                    ->columnSpanFull(),

                // ── Issued Stock Error Banner ──────────────────────────────────
                // Visible when status=issued AND quantities exceed stock.
                // Hard block — no toggle, cannot be saved as issued.
                Placeholder::make('issued_stock_error_banner')
                    ->label('')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => self::hasStockIssuesForIssued($get))
                    ->content(function (Get $get): HtmlString {
                        $rows = self::buildWarningRows($get, '#fecaca');
                        if (!$rows) return new HtmlString('');

                        return new HtmlString("
                            <div style='border:2px solid #dc2626;border-radius:8px;overflow:hidden;background:#fef2f2;'>
                                <div style='background:#dc2626;padding:10px 14px;display:flex;align-items:center;gap:8px;'>
                                    <span style='font-size:18px;'>⛔</span>
                                    <span style='font-size:13px;font-weight:700;color:#fff;'>
                                        Cannot Issue — Quantities Exceed Available Stock
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
                                        This issuance <strong>cannot be saved as Issued</strong> while stock is insufficient.<br>
                                        Please <strong>reduce the quantities</strong>, <strong>replenish stock</strong>,
                                        or change the status to <strong>Pending</strong> to save without deducting stock.
                                    </div>
                                </div>
                            </div>
                        ");
                    }),

                // ── Pending Stock Warning Banner ───────────────────────────────
                // Visible when status=pending AND quantities exceed stock.
                // Soft block — requires toggle acknowledgement to save.
                Placeholder::make('pending_stock_warning_banner')
                    ->label('')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => self::hasStockIssues($get))
                    ->content(function (Get $get): HtmlString {
                        $rows = self::buildWarningRows($get, '#fde68a');
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
                                        This issuance will be saved as <strong>Pending</strong> and
                                        <strong>cannot be issued</strong> until stock is replenished
                                        or quantities are reduced.<br>
                                        <strong>Toggle the switch below to confirm and save anyway.</strong>
                                    </div>
                                </div>
                            </div>
                        ");
                    }),

                // ── Pending Stock Warning Toggle ───────────────────────────────
                // Only appears when there are stock issues on a pending issuance.
                // NO ->live() — same reason as the PO form: live() causes Livewire
                // to re-render on every toggle click, resetting Alpine.js state.
                Toggle::make('stock_warning_acknowledged')
                    ->label('I understand the stock shortage — save this issuance as Pending anyway')
                    ->columnSpanFull()
                    ->default(false)
                    ->onColor('warning')
                    ->offColor('gray')
                    ->visible(fn (Get $get): bool => self::hasStockIssues($get)),

            ]);
    }
}