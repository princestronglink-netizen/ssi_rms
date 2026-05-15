<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('department_id')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function ($record, array $data) {
                        $rolePermissionIds = $data['role_permission_ids'] ?? [];
                        $selectedIds       = $data['permissions'] ?? [];

                        $extraIds = array_diff($selectedIds, $rolePermissionIds);
                        $removedIds = array_diff($rolePermissionIds, $selectedIds);

                        $deniedNames = Permission::whereIn('id', $removedIds)
                            ->pluck('name')
                            ->toArray();

                        $record->denied_permissions = $deniedNames;
                        $record->save();

                        $extraPermissions = Permission::whereIn('id', $extraIds)->get();
                        $record->syncPermissions($extraPermissions);
                    }),

                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset User Password')
                    ->modalDescription(fn ($record) => "This will generate a new temporary password for {$record->name} ({$record->email}). The old password will no longer work.")
                    ->modalSubmitActionLabel('Yes, Reset Password')
                    ->action(function ($record) {
                        $newPassword = Str::random(12);

                        $record->password = Hash::make($newPassword);
                        $record->save();

                        Notification::make()
                            ->title('🔑 Password Reset Successfully')
                            ->body(
                                "👤 **{$record->name}**\n" .
                                "📧 {$record->email}\n\n" .
                                "🔐 New Temporary Password:\n" .
                                "**{$newPassword}**\n\n" .
                                "Share this with the user and ask them to change it upon first login."
                            )
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}