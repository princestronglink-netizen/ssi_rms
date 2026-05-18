<?php

namespace App\Filament\Resources\SmeCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SmeCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sme_category_name')
                    ->label('Category Name')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
