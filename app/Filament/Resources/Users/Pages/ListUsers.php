<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public ?string $tempPassword = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $this->tempPassword  = Str::random(12);
                    $data['password']    = $this->tempPassword;

                    $rolePermissionIds = $data['role_permission_ids'] ?? [];
                    $selectedIds       = $data['permissions'] ?? [];

                    // Denied = role permissions the admin unchecked
                    $removedIds      = array_diff($rolePermissionIds, $selectedIds);
                    $deniedNames     = Permission::whereIn('id', $removedIds)->pluck('name')->toArray();

                    $data['denied_permissions'] = $deniedNames;

                    return $data;
                })
                ->after(function ($record, array $data) {
                    $rolePermissionIds = $data['role_permission_ids'] ?? [];
                    $selectedIds       = $data['permissions'] ?? [];

                    // Extra = permissions added beyond what the role grants
                    $extraIds         = array_diff($selectedIds, $rolePermissionIds);
                    $extraPermissions = Permission::whereIn('id', $extraIds)->get();

                    $record->syncPermissions($extraPermissions);

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
                        ->persistent()
                        ->send();
                }),
        ];
    }
}