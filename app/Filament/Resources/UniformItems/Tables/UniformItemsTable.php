<?php

namespace App\Filament\Resources\UniformItems\Tables;

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
use App\Filament\Imports\UniformItemsImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ViewField;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Storage;

class UniformItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('uniform_item_image')->circular(),

                TextColumn::make('uniform_category_id')
                    ->label('Category')
                    ->sortable(),

                TextColumn::make('uniform_item_name')->searchable(),
                TextColumn::make('uniform_item_description')->searchable(),
                TextColumn::make('uniform_item_price')->searchable(),

                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
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

                // ImportAction::make()
                //     ->label('Import CSV / Excel')
                //     ->icon('heroicon-o-arrow-up-tray')
                //     ->importer(UniformItemsImporter::class)
                //     ->chunkSize(100),

                // Action::make('downloadTemplate')
                //     ->label('Download Template')
                //     ->icon('heroicon-o-arrow-down-tray')
                //     ->color('success')
                //     ->url(route('uniform-items.template'))
                //     ->openUrlInNewTab(),
            ]);
    }
}