<?php

namespace App\Filament\Resources\UniformItems\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use App\Models\UniformItems;
use App\Rules\UniqueVariantSize;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;

class UniformItemsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('uniform_category_id')
                    ->relationship('category', 'uniform_category_name')
                    ->required(),
                TextInput::make('uniform_item_name')
                    ->unique(
                        table: 'uniform_items',
                        column: 'uniform_item_name',
                        ignoreRecord: true,
                    )
                    ->required(),
                TextInput::make('uniform_item_description'),
                TextInput::make('uniform_item_price')
                    ->required()
                    ->numeric(),
                FileUpload::make('uniform_item_image')
                    ->image()
                    ->directory('uniform-items')
                    ->nullable(),
                Repeater::make('uniform_item_variants')
                    ->relationship('itemVariant')
                    ->schema([
                        // Hidden field to carry the variant's own ID through the form state
                        Hidden::make('id'),

                        TextInput::make('uniform_item_size')
                            ->rules([
                                fn($get) => new UniqueVariantSize(
                                    itemName: $get('../../uniform_item_name'),
                                    allSizes: collect($get('../../uniform_item_variants'))
                                        ->pluck('uniform_item_size')
                                        ->filter()
                                        ->values()
                                        ->toArray(),
                                    currentVariantId: (int) $get('id') ?: null,
                                )
                            ])
                            ->required(),

                        TextInput::make('uniform_item_quantity')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2)
                    ->columnSpan('full')
                    ->collapsible()
            ]);
    }
}