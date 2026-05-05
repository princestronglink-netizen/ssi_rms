<?php

namespace App\Filament\Resources\Transmittals\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransmittalsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        TextInput::make('uniform_issuance_id')
                            ->numeric()
                            ->hidden(),
                        TextInput::make('transmittal_number')
                            ->default(fn () => \App\Models\Transmittals::generateNumber())
                            ->readOnly()
                            ->required(),
                        TextInput::make('transmitted_by')
                            ->required(),
                        TextInput::make('transmitted_to')
                            ->required(),
                    ])
                    ->columnSpanFull(),

                Repeater::make('items_summary')
                    ->label('Items Summary')
                    ->schema([
                        TextInput::make('item')
                            ->label('Item')
                            ->required()
                            ->placeholder('Enter item description'),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->placeholder('e.g. 1'),
                        TextInput::make('remarks')
                            ->label('Remarks')
                            ->placeholder('Optional remarks'),
                    ])
                    ->columns(3)
                    ->addActionLabel('Add Item')
                    ->required()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->collapsible()
                    ->cloneable()
                    ->columnSpanFull(),

                Grid::make(2)
                    ->schema([
                        TextInput::make('purpose'),
                        TextInput::make('instructions'),
                        DatePicker::make('transmitted_at')
                            ->hidden()
                            ->default(now()->timezone('Asia/Manila'))
                            ->required(),
                        Select::make('status')
                            ->options(['pending' => 'Pending', 'received' => 'Received'])
                            ->default('pending')
                            ->required()
                            ->hidden(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}