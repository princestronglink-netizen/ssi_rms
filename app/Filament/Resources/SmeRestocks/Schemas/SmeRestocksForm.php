<?php

namespace App\Filament\Resources\SmeRestocks\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use App\Models\SmeItems;
use App\Models\SmeItemVariants;

class SmeRestocksForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('supplier_name')
                    ->label('Supplier Name')
                    ->required(),

                TextInput::make('ordered_by')
                    ->label('Ordered By')
                    ->default(auth()->user()->name)
                    ->required(),

                Select::make('status')
                    ->options([
                        'pending'   => 'Pending',
                        'delivered' => 'Delivered',
                    ])
                    ->required()
                    ->live()
                    ->default('pending')
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state === 'pending') {
                            $set('pending_at', now()->toDateString());
                            $set('delivered_at', null);
                        }
                        if ($state === 'delivered') {
                            $set('delivered_at', now()->toDateString());
                            $set('pending_at', null);
                        }

                        $items = $get('smeRestockItem') ?? [];
                        foreach ($items as $iKey => $item) {
                            $qty = (int) ($item['quantity'] ?? 0);
                            if ($state === 'delivered') {
                                $set("smeRestockItem.{$iKey}.delivered_quantity", $qty);
                                $set("smeRestockItem.{$iKey}.remaining_quantity", 0);
                            } else {
                                $set("smeRestockItem.{$iKey}.delivered_quantity", 0);
                                $set("smeRestockItem.{$iKey}.remaining_quantity", $qty);
                            }
                        }
                    }),

                DatePicker::make('pending_at')
                    ->label('Pending Date')
                    ->default(now()->toDateString())
                    ->live()
                    ->visible(fn ($get) => $get('status') === 'pending'),

                DatePicker::make('delivered_at')
                    ->label('Delivered Date')
                    ->live()
                    ->visible(fn ($get) => $get('status') === 'delivered'),

                Textarea::make('notes')
                    ->label('Notes')
                    ->columnSpanFull(),

                // ── Items Repeater ─────────────────────────────────────────
                Repeater::make('smeRestockItem')
                    ->label('Restock Items')
                    ->relationship('smeRestockItem')
                    ->addActionLabel('+ Add Item')
                    ->minItems(1)
                    ->defaultItems(1)
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('sme_item_id')
                            ->label('Item')
                            ->options(SmeItems::pluck('sme_item_name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (callable $set) {
                                $set('sme_item_variant_id', null);
                            }),

                        Select::make('sme_item_variant_id')
                            ->label('Size / Variant')
                            ->options(function (callable $get) {
                                $itemId = $get('sme_item_id');
                                if (!$itemId) return [];
                                return SmeItemVariants::where('sme_item_id', $itemId)
                                    ->pluck('sme_item_size', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->live(),

                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $qty    = (int) $state;
                                $status = $get('../../status') ?? 'pending';
                                if ($status === 'delivered') {
                                    $set('delivered_quantity', $qty);
                                    $set('remaining_quantity', 0);
                                } else {
                                    $set('delivered_quantity', 0);
                                    $set('remaining_quantity', $qty);
                                }
                            }),

                        TextInput::make('delivered_quantity')
                            ->label('Delivered Qty')
                            ->numeric()
                            ->default(0)
                            ->hidden()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(true),

                        TextInput::make('remaining_quantity')
                            ->label('Remaining Qty')
                            ->numeric()
                            ->default(0)
                            ->hidden()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(true),
                    ])
                    ->columns(3),
            ]);
    }
}