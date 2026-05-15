<?php

namespace App\Filament\Resources\UniformIssuances\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;
use App\Models\UniformItemVariants;
use App\Models\UniformItems;
use App\Models\UniformSets;
use App\Models\UniformSetItems;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use App\Models\UniformIssuanceLog;

class UniformIssuancesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->required()
                    ->relationship('site', 'site_name')
                    ->searchable()
                    ->preload(),

                // ── Removed top-level uniform_issuance_type_id ──
                // Type is now set per-item inside the repeater

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
                DatePicker::make('pending_at')
                    ->default(now()->toDateString())
                    ->live()
                    ->visible(fn ($get) => $get('uniform_issuance_status') === 'pending'),
                DatePicker::make('issued_at')
                    ->live()
                    ->visible(fn ($get) => $get('uniform_issuance_status') === 'issued'),
                Textarea::make('notes')
                    ->columnSpanFull(),

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
                                        'uniform_item_id'           => $item->uniform_item_id,
                                        'uniform_item_variant_id'   => null,
                                        'uniform_issuance_type_id'  => null, // will need manual selection
                                        'quantity'                  => $item->quantity,
                                    ])
                                    ->toArray();

                                $set('uniformIssuanceItem', $setItems);
                            }),

                        Repeater::make('uniformIssuanceItem')
                            ->relationship('uniformIssuanceItem')
                            ->schema([
                                // ── Issuance Type per item ──────────────────
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
                                                $qty = (int) $value;
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

                                Placeholder::make('stock_summary')
                                    ->content(function (callable $get) {
                                        $variantId = $get('uniform_item_variant_id');
                                        $qty = (int) ($get('quantity') ?? 0);
                                        if (!$variantId) return new HtmlString('<span style="color:#9ca3af;">Select a variant to see stock.</span>');
                                        $variant = UniformItemVariants::find($variantId);
                                        if (!$variant) return new HtmlString('<span style="color:#dc2626;">Variant not found.</span>');
                                        $stock = (int) $variant->uniform_item_quantity;
                                        $remaining = $stock - $qty;
                                        $color = $remaining < 0 ? '#dc2626' : ($remaining === 0 ? '#d97706' : '#16a34a');
                                        $status = $remaining < 0
                                            ? "⛔ Over by " . abs($remaining)
                                            : ($remaining === 0 ? "⚠️ Exact stock" : "✅ {$remaining} remaining after issuance");
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

                // ─── GLOBAL SUMMARY ──────────────────────────────────────────────────────
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

                                $grandTotal += $qty;
                                // Key now includes type so same item/variant in different types shows separately
                                $key = ($itemId ?? 'null') . '_' . ($variantId ?? 'null') . '_' . ($typeId ?? 'null');

                                if (!isset($aggregated[$key])) {
                                    $itemModel    = $itemId    ? UniformItems::find($itemId)           : null;
                                    $variantModel = $variantId ? UniformItemVariants::find($variantId) : null;
                                    $typeModel    = $typeId    ? \App\Models\UniformIssuanceType::find($typeId) : null;

                                    $aggregated[$key] = [
                                        'item_name'    => $itemModel?->uniform_item_name    ?? '—',
                                        'variant_name' => $variantModel?->uniform_item_size ?? '—',
                                        'type_name'    => $typeModel?->uniform_issuance_type_name ?? '—',
                                        'stock'        => (int) ($variantModel?->uniform_item_quantity ?? 0),
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
                            $enough      = $data['total_qty'] <= $data['stock'];
                            $statusColor = $enough ? '#16a34a' : '#dc2626';
                            $statusIcon  = $enough ? '✅' : '⛔';

                            $rows .= "
                                <tr>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;'>{$data['item_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$data['variant_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:#7c3aed;'>{$data['type_name']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;font-weight:700;'>{$data['total_qty']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:#374151;'>{$data['stock']}</td>
                                    <td style='padding:4px 8px;font-size:11px;border-bottom:1px solid #e5e7eb;text-align:center;color:{$statusColor};'>{$statusIcon}</td>
                                </tr>";
                        }

                        return new HtmlString("
                            <table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;'>
                                <thead>
                                    <tr style='background:#1e3a5f;'>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:left;'>Item</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Size</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Type</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>Qty</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>In Stock</th>
                                        <th style='padding:6px 8px;font-size:10px;color:#fff;text-align:center;'>OK?</th>
                                    </tr>
                                </thead>
                                <tbody>{$rows}</tbody>
                                <tfoot>
                                    <tr style='background:#f8fafc;'>
                                        <td colspan='3' style='padding:5px 8px;font-size:11px;font-weight:700;color:#374151;'>Total Items</td>
                                        <td style='padding:5px 8px;font-size:12px;font-weight:900;color:#1d4ed8;text-align:center;'>{$grandTotal}</td>
                                        <td colspan='2'></td>
                                    </tr>
                                </tfoot>
                            </table>
                        ");
                    })
                    ->columnSpanFull(),
            ]);
    }
}