<?php

namespace App\Filament\Resources\OfficeSupplyItems\Tables;

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
use App\Filament\Imports\OfficeSupplyItemsImporter;

class OfficeSupplyItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('office_supply_image')
                    ->circular(),

                TextColumn::make('category.office_supply_category_name')
                    ->label('Category')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('office_supply_name')
                    ->searchable(),

                TextColumn::make('office_supply_price')
                    ->money()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
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
                    ->importer(OfficeSupplyItemsImporter::class)
                    ->chunkSize(100),

                Action::make('downloadOfficeSupplyTemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(route('office-supply-items.template'))
                    ->openUrlInNewTab(),
            ]);
    }
}