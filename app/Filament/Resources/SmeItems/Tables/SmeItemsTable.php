<?php

namespace App\Filament\Resources\SmeItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\ImportAction;
use Filament\Actions\Action;
use App\Filament\Imports\SmeItemsImporter;

class SmeItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('sme_item_image')
                    ->circular(),

                TextColumn::make('sme_category_id')
                    ->label('Category')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('sme_item_name')
                    ->searchable(),

                TextColumn::make('sme_item_brand')
                    ->searchable(),

                TextColumn::make('sme_item_description')
                    ->searchable(),

                TextColumn::make('sme_item_price')
                    ->searchable(),

                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),

                ImportAction::make()
                    ->label('Import CSV / Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->importer(SmeItemsImporter::class)
                    ->chunkSize(100),

                Action::make('downloadSmeTtemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(route('sme-items.template'))
                    ->openUrlInNewTab(),
            ]);
    }
}