<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public ?string $tempPassword = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $this->tempPassword = Str::random(12);
                    $data['password'] = $this->tempPassword; // hashed automatically via cast
                    return $data;
                })
                ->after(function ($record) {
                    Notification::make()
                        ->title('✅ User Created — Temporary Password')
                        ->body(
                            "👤 **{$record->name}**\n" .
                            "📧 {$record->email}\n\n" .
                            "🔐 Temporary Password:\n" .
                            "**{$this->tempPassword}**\n\n" .
                            "Share this with the user and ask them to change it upon first login."
                        )
                        ->success()
                        ->persistent() // stays until dismissed
                        ->send();
                }),
        ];
    }
}