<?php

namespace App\Filament\Resources\UniformIssuances\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
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
    // Stock helpers  (unchanged — pure data logic, no UI concerns)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Aggregate total quantity ordered per variant across ALL recipients
     * and ALL their items. Used by both the form banners and the page action.
     */
    private static function aggregateStock(Get $get): array
    {
        $aggregate = [];

        foreach ($get('uniformIssuanceRecipient') ?? [] as $recipient) {
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
     * $accentColor drives the header bar; row borders/text derive from it.
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
                    <td style='padding:8px 12px;font-size:12.5px;border-bottom:1px solid {$borderColor};'>
                        <strong>{$size}</strong>
                    </td>
                    <td style='padding:8px 12px;font-size:12.5px;border-bottom:1px solid {$borderColor};text-align:center;color:#1d4ed8;font-weight:700;'>
                        {$totalOrdered}
                    </td>
                    <td style='padding:8px 12px;font-size:12.5px;border-bottom:1px solid {$borderColor};text-align:center;'>
                        {$stock}
                    </td>
                    <td style='padding:8px 12px;font-size:12.5px;border-bottom:1px solid {$borderColor};text-align:center;color:#dc2626;font-weight:700;'>
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
            ->columns(1)
            ->components([

                // ── Issuance Overview ────────────────────────────────────────
                // Site, status, dates and notes live together as the "header"
                // of the record — a single card instead of loose top-level fields.
                Section::make('Issuance Overview')
                    ->description('Where this issuance is for and what state it is in.')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columns(3)
                    ->schema([
                        Select::make('site_id')
                            ->label('Site')
                            ->required()
                            ->relationship('site', 'site_name')
                            ->searchable()
                            ->preload()
                            ->prefixIcon('heroicon-o-building-office-2'),

                        Select::make('uniform_issuance_status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Pending',
                                'issued'  => 'Issued',
                            ])
                            ->required()
                            ->live()
                            ->native(false)
                            ->default('pending')
                            ->prefixIcon('heroicon-o-adjustments-horizontal')
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

                        DatePicker::make('pending_at')
                            ->label('Pending Date')
                            ->default(now()->toDateString())
                            ->live()
                            ->prefixIcon('heroicon-o-calendar-days')
                            ->visible(fn (Get $get) => $get('uniform_issuance_status') === 'pending'),

                        DatePicker::make('issued_at')
                            ->label('Issued Date')
                            ->live()
                            ->prefixIcon('heroicon-o-calendar-days')
                            ->visible(fn (Get $get) => $get('uniform_issuance_status') === 'issued'),

                        Textarea::make('notes')
                            ->rows(2)
                            ->placeholder('Any additional context for this issuance…')
                            ->columnSpanFull(),
                    ]),

                // ── Recipients ────────────────────────────────────────────────
                // Each recipient collapses to a labelled card once filled in,
                // so a form with several people stays scannable instead of
                // becoming one long unbroken scroll. The "Add Recipient" button
                // lives in the section header, not buried at the bottom, so it
                // reads as an action that belongs to this card.
                Section::make('Recipients')
                    ->description('Add every employee who is part of this issuance and the items they will receive.')
                    ->icon('heroicon-o-users')
                    ->headerActions([
                        Action::make('add_recipient')
                            ->label('Add Recipient')
                            ->icon('heroicon-o-user-plus')
                            ->color('primary')
                            ->action(function (Get $get, Set $set) {
                                $recipients = $get('uniformIssuanceRecipient') ?? [];

                                $recipients[] = [
                                    'transaction_id'  => null,
                                    'employee_name'   => null,
                                    'employee_status' => null,
                                    'position_id'     => null,
                                    'uniform_set_id'  => null,
                                    'itemGroups'      => [],
                                ];

                                $set('uniformIssuanceRecipient', $recipients);
                            }),
                    ])
                    ->columns(1)
                    ->schema([
                        Repeater::make('uniformIssuanceRecipient')
                            // ->relationship('uniformIssuanceRecipient')
                            ->label('Recipients')
                            ->hiddenLabel()
                            ->addable(false)
                            ->deleteAction(fn ($action) => $action
                                ->label('Remove Recipient')
                                ->icon('heroicon-o-trash')
                                ->color('danger')
                                ->tooltip('Remove this recipient'))
                            ->cloneAction(fn ($action) => $action
                                ->label('Duplicate Recipient')
                                ->icon('heroicon-o-document-duplicate')
                                ->tooltip('Duplicate this recipient'))
                            ->columns(3)
                            ->schema([
                                TextInput::make('transaction_id')
                                    ->hidden()
                                    ->placeholder('Auto-generated'),

                                TextInput::make('employee_name')
                                    ->label('Employee Name')
                                    ->required()
                                    ->prefixIcon('heroicon-o-user')
                                    ->live(onBlur: true)
                                    ->columnSpanFull(),

                                Select::make('employee_status')
                                    ->label('Employee Status')
                                    ->options([
                                        'reliever' => 'Reliever',
                                        'posted'   => 'Posted',
                                    ])
                                    ->required()
                                    ->native(false),

                                Select::make('position_id')
                                    ->label('Position')
                                    ->options(fn () => \App\Models\Positions::pluck('position_name', 'id'))
                                    ->required()
                                    ->preload()
                                    ->searchable(),

                                Select::make('uniform_set_id')
                                    ->label('Uniform Set')
                                    ->helperText('Auto-fills the item list below.')
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

                                        $setItems = UniformSetItems::where('uniform_set_id', $state)->get();

                                        $set('itemGroups', [[
                                            'uniform_issuance_type_id' => null,
                                            'items' => $setItems->map(fn ($item) => [
                                                'uniform_item_id'         => $item->uniform_item_id,
                                                'uniform_item_variant_id' => null,
                                                'quantity'                => $item->quantity,
                                            ])->toArray(),
                                        ]]);
                                    }),

                                // ── Items per Recipient, grouped by Issuance Type ──
                                Section::make('Uniform Items')
                                    ->description('Group the items this recipient gets by issuance type.')
                                    ->icon('heroicon-o-squares-2x2')
                                    ->headerActions([
                                        Action::make('add_issuance_type_group')
                                            ->label('Add Issuance Type Group')
                                            ->icon('heroicon-o-plus-circle')
                                            ->color('primary')
                                            ->action(function (Get $get, Set $set) {
                                                $groups = $get('itemGroups') ?? [];

                                                $groups[] = [
                                                    'uniform_issuance_type_id' => null,
                                                    'items' => [],
                                                ];

                                                $set('itemGroups', $groups);
                                            }),
                                    ])
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('itemGroups')
                                            ->hiddenLabel()
                                            ->addable(false)
                                            ->deleteAction(fn ($action) => $action
                                                ->label('Remove Group')
                                                ->icon('heroicon-o-trash')
                                                ->color('danger')
                                                ->tooltip('Remove this issuance type group'))
                                            ->itemLabel(fn (array $state): ?string =>
                                                $state['uniform_issuance_type_id']
                                                    ? (\App\Models\UniformIssuanceType::find($state['uniform_issuance_type_id'])?->uniform_issuance_type_name ?? 'Issuance Type')
                                                    : 'New Issuance Type Group'
                                            )
                                            ->collapsible()
                                            ->columns(1)
                                            ->schema([
                                                Select::make('uniform_issuance_type_id')
                                                    ->label('Issuance Type')
                                                    ->options(fn () => \App\Models\UniformIssuanceType::pluck('uniform_issuance_type_name', 'id'))
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-o-tag')
                                                    ->columnSpanFull(),

                                                Section::make('Items')
                                                    ->description('The specific item, size, and quantity for this type.')
                                                    ->icon('heroicon-o-archive-box')
                                                    ->headerActions([
                                                        Action::make('add_item')
                                                            ->label('Add Item')
                                                            ->icon('heroicon-o-plus')
                                                            ->color('primary')
                                                            ->action(function (Get $get, Set $set) {
                                                                $items = $get('items') ?? [];

                                                                $items[] = [
                                                                    'uniform_item_id'         => null,
                                                                    'uniform_item_variant_id' => null,
                                                                    'quantity'                => null,
                                                                    'released_quantity'       => 0,
                                                                    'remaining_quantity'      => 0,
                                                                ];

                                                                $set('items', $items);
                                                            }),
                                                    ])
                                                    ->columnSpanFull()
                                                    ->schema([
                                                        Repeater::make('items')
                                                            ->hiddenLabel()
                                                            ->addable(false)
                                                            ->deleteAction(fn ($action) => $action
                                                                ->label('Remove Item')
                                                                ->icon('heroicon-o-x-mark')
                                                                ->color('danger')
                                                                ->tooltip('Remove this item'))
                                                            ->minItems(1)
                                                            ->itemLabel(fn (array $state): ?string =>
                                                                $state['uniform_item_id']
                                                                    ? (UniformItems::find($state['uniform_item_id'])?->uniform_item_name ?? 'Item')
                                                                    : 'New Item'
                                                            )
                                                            ->columns(3)
                                                            ->schema([
                                                                TextInput::make('id')->hidden()->dehydrated(), // keeps DB id on edit

                                                                Select::make('uniform_item_id')
                                                                    ->label('Item')
                                                                    ->options(UniformItems::pluck('uniform_item_name', 'id'))
                                                                    ->required()
                                                                    ->searchable()
                                                                    ->reactive()
                                                                    ->afterStateUpdated(fn (callable $set) => $set('uniform_item_variant_id', null)),

                                                                Select::make('uniform_item_variant_id')
                                                                    ->label('Size / Variant')
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
                                                                        return "Stock: " . (int) $variant->uniform_item_quantity;
                                                                    })
                                                                    ->hintColor(function (callable $get) {
                                                                        $variantId = $get('uniform_item_variant_id');
                                                                        if (!$variantId) return null;
                                                                        $variant = UniformItemVariants::find($variantId);
                                                                        if (!$variant) return null;
                                                                        return ((int) $variant->uniform_item_quantity) > 0 ? 'success' : 'danger';
                                                                    }),

                                                                TextInput::make('quantity')
                                                                    ->numeric()
                                                                    ->required()
                                                                    ->live()
                                                                    ->prefixIcon('heroicon-o-hashtag')
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

                                                                TextInput::make('released_quantity')->numeric()->default(0)->hidden()->dehydrated(),
                                                                TextInput::make('remaining_quantity')->numeric()->default(0)->hidden()->dehydrated(),

                                                                Placeholder::make('stock_summary')
                                                                    ->label('')
                                                                    ->content(function (callable $get) {
                                                                        $variantId = $get('uniform_item_variant_id');
                                                                        $qty       = (int) ($get('quantity') ?? 0);

                                                                        if (!$variantId) {
                                                                            return new HtmlString('<span style="font-size:12px;color:#9ca3af;">Select a variant to see stock.</span>');
                                                                        }
                                                                        $variant = UniformItemVariants::find($variantId);
                                                                        if (!$variant) {
                                                                            return new HtmlString('<span style="font-size:12px;color:#dc2626;">Variant not found.</span>');
                                                                        }
                                                                        $stock     = (int) $variant->uniform_item_quantity;
                                                                        $remaining = $stock - $qty;
                                                                        $color     = $remaining < 0 ? '#dc2626' : ($remaining === 0 ? '#d97706' : '#16a34a');
                                                                        $status    = $remaining < 0
                                                                            ? '⛔ Over by ' . abs($remaining)
                                                                            : ($remaining === 0 ? '⚠️ Exact stock' : "✅ {$remaining} remaining after issuance");

                                                                        return new HtmlString("
                                                                            <div style='font-size:12px;padding-top:2px;'>
                                                                                <span style='color:#374151;'>In stock: <strong>{$stock}</strong></span>
                                                                                &nbsp;|&nbsp;
                                                                                <span style='color:{$color};font-weight:600;'>{$status}</span>
                                                                            </div>
                                                                        ");
                                                                    })
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columnSpanFull(),
                                                    ]),
                                            ])
                                            ->columnSpanFull()
                                            ->live(),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['employee_name'] ?? 'New Recipient')
                            ->collapsible()
                            ->columnSpanFull()
                            ->live(),
                    ]),

                // ── Summary & Stock Health ───────────────────────────────────
                // Grand-total table plus the two banners/toggle are grouped
                // together as a single "results" section that appears after
                // the data entry, instead of being scattered top-to-bottom.
                Section::make('Summary & Stock Health')
                    ->description('A live rollup of everything entered above, plus any stock conflicts.')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(1)
                    ->schema([
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
                                    foreach ($recipient['itemGroups'] ?? [] as $group) {
                                        $typeId = $group['uniform_issuance_type_id'] ?? null;

                                        foreach ($group['items'] ?? [] as $item) {
                                            $variantId = $item['uniform_item_variant_id'] ?? null;
                                            $itemId    = $item['uniform_item_id'] ?? null;
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
                                        } // end items loop
                                    } // end itemGroups loop
                                } // end recipients loop

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
                                            <td style='padding:6px 10px;font-size:11.5px;border-bottom:1px solid #e5e7eb;'>{$data['item_name']}</td>
                                            <td style='padding:6px 10px;font-size:11.5px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$data['variant_name']}</td>
                                            <td style='padding:6px 10px;font-size:11.5px;border-bottom:1px solid #e5e7eb;text-align:center;color:#7c3aed;'>{$data['type_name']}</td>
                                            <td style='padding:6px 10px;font-size:11.5px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:700;color:#1d4ed8;'>{$data['total_qty']}</td>
                                            <td style='padding:6px 10px;font-size:11.5px;border-bottom:1px solid #e5e7eb;text-align:center;color:#374151;'>{$data['stock']}</td>
                                            <td style='padding:6px 10px;font-size:11.5px;border-bottom:1px solid #e5e7eb;text-align:center;color:{$remainColor};font-weight:600;'>{$statusIcon} {$remainLabel}</td>
                                        </tr>";
                                }

                                return new HtmlString("
                                    <div style='border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;'>
                                        <table style='width:100%;border-collapse:collapse;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:8px 10px;font-size:10.5px;color:#fff;text-align:left;'>Item</th>
                                                    <th style='padding:8px 10px;font-size:10.5px;color:#fff;text-align:center;'>Size</th>
                                                    <th style='padding:8px 10px;font-size:10.5px;color:#fff;text-align:center;'>Type</th>
                                                    <th style='padding:8px 10px;font-size:10.5px;color:#fff;text-align:center;'>Total Issued</th>
                                                    <th style='padding:8px 10px;font-size:10.5px;color:#fff;text-align:center;'>In Stock</th>
                                                    <th style='padding:8px 10px;font-size:10.5px;color:#fff;text-align:center;'>Remaining Stock</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$rows}</tbody>
                                            <tfoot>
                                                <tr style='background:#f8fafc;'>
                                                    <td colspan='3' style='padding:7px 10px;font-size:11.5px;font-weight:700;color:#374151;'>Grand Total</td>
                                                    <td style='padding:7px 10px;font-size:12.5px;font-weight:900;color:#1d4ed8;text-align:center;'>{$grandTotal}</td>
                                                    <td colspan='2'></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),

                        // ── Issued Stock Error Banner ──────────────────────────
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
                                    <div style='border:1px solid #fca5a5;border-radius:10px;overflow:hidden;background:#fef2f2;'>
                                        <div style='background:#dc2626;padding:12px 16px;display:flex;align-items:center;gap:8px;'>
                                            <span style='font-size:18px;'>⛔</span>
                                            <span style='font-size:13px;font-weight:700;color:#fff;'>
                                                Cannot Issue — Quantities Exceed Available Stock
                                            </span>
                                        </div>
                                        <div style='padding:14px 16px;'>
                                            <table style='width:100%;border-collapse:collapse;margin-bottom:10px;border-radius:6px;overflow:hidden;'>
                                                <thead>
                                                    <tr style='background:#b91c1c;'>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:left;'>Variant</th>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:center;'>Ordered</th>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:center;'>In Stock</th>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:center;'>Shortfall</th>
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

                        // ── Pending Stock Warning Banner ───────────────────────
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
                                    <div style='border:1px solid #fcd34d;border-radius:10px;overflow:hidden;background:#fffbeb;'>
                                        <div style='background:#f59e0b;padding:12px 16px;display:flex;align-items:center;gap:8px;'>
                                            <span style='font-size:18px;'>⚠️</span>
                                            <span style='font-size:13px;font-weight:700;color:#fff;'>
                                                Stock Warning — Quantities Exceed Available Stock
                                            </span>
                                        </div>
                                        <div style='padding:14px 16px;'>
                                            <table style='width:100%;border-collapse:collapse;margin-bottom:10px;border-radius:6px;overflow:hidden;'>
                                                <thead>
                                                    <tr style='background:#d97706;'>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:left;'>Variant</th>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:center;'>Ordered</th>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:center;'>In Stock</th>
                                                        <th style='padding:7px 12px;font-size:11px;color:#fff;text-align:center;'>Shortfall</th>
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

                        // ── Pending Stock Warning Toggle ───────────────────────
                        // Only appears when there are stock issues on a pending issuance.
                        // NO ->live() — same reason as the PO form: live() causes Livewire
                        // to re-render on every toggle click, resetting Alpine.js state.
                        Toggle::make('stock_warning_acknowledged')
                            ->label('I understand the stock shortage — save this issuance as Pending anyway')
                            ->columnSpanFull()
                            ->default(false)
                            ->onColor('warning')
                            ->offColor('gray')
                            ->inline(false)
                            ->visible(fn (Get $get): bool => self::hasStockIssues($get)),
                    ]),

            ]);
    }
}