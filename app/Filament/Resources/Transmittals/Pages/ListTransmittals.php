<?php

namespace App\Filament\Resources\Transmittals\Pages;

use App\Filament\Resources\Transmittals\TransmittalsResource;
use App\Models\Transmittals;
use App\Models\TransmittalLog;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransmittals extends ListRecords
{
    protected static string $resource = TransmittalsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['transmittal_number'] = Transmittals::generateNumber();
                    return $data;
                })
                ->after(function ($record) {
                    TransmittalLog::create([
                        'transmittal_id' => $record->id,
                        'user_id'        => auth()->id(),
                        'action'         => 'Created',
                        'status_from'    => null,
                        'status_to'      => 'pending',
                        'note'           => "Transmittal {$record->transmittal_number} created.",
                    ]);
                }),
        ];
    }
}