<?php

namespace App\Filament\Resources\UniformItems\Pages;

use App\Filament\Resources\UniformItems\UniformItemsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUniformItems extends ListRecords
{
    protected static string $resource = UniformItemsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->extraAttributes([
                    'style' => 'color: #ffffff;' // dark text
                ]),
                // \Filament\Actions\ImportAction::make()
                //     ->importer(\App\Filament\Imports\UniformItemsImporter::class)
                //     ->job(\App\Jobs\ImportUniformItemsJob::class)
                //     ->label('Import Items')
                //     ->icon('heroicon-o-arrow-up-tray'),
        ];
    }
}
