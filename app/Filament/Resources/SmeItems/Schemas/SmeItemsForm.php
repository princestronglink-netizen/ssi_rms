<?php

namespace App\Filament\Resources\SmeItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use App\Rules\UniqueVariantSize;

class SmeItemsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('sme_category_id')
                    ->label('Category')
                    ->relationship('category', 'sme_category_name')
                    ->required(),
                TextInput::make('sme_item_name')
                    ->label('Item Name')
                    ->unique(
                        table: 'sme_items',
                        column: 'sme_item_name',
                        ignoreRecord: true,
                    )
                    ->required(),
                TextInput::make('sme_item_brand')
                    ->label('Brand')
                    ->required(),
                TextInput::make('sme_item_price')
                    ->label('Price')
                    ->required()
                    ->numeric()
                    ->nullable()
                    ->hidden(),
                Textarea::make('sme_item_description')
                    ->label('Description')
                    ->columnSpanFull(),
                FileUpload::make('sme_item_image')
                    ->image()
                    ->directory('sme-items')
                    ->nullable()
                    ->columnSpanFull(),
                Repeater::make('sme_item_variants')
                    ->relationship('itemVariant')
                    ->schema([
                        TextInput::make('sme_item_size')
                            ->label('Size')
                            ->rules([
                                fn($get, $record) => new UniqueVariantSize(
                                    itemName: $get('../../sme_item_name'),
                                    allSizes: collect($get('../../sme_item_variants'))
                                        ->pluck('sme_item_size')
                                        ->filter()
                                        ->values()
                                        ->toArray(),
                                    currentVariantId: $record?->id,
                                )
                            ])
                            ->required(),

                        TextInput::make('sme_item_quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsible()
            ]);
    }
}