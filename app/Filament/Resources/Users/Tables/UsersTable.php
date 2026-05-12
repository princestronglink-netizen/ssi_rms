<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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

                        // Permissions added on top of the role
                        $extraIds = array_diff($selectedIds, $rolePermissionIds);

                        // Permissions removed from what the role grants → these are denied
                        $removedIds = array_diff($rolePermissionIds, $selectedIds);

                        // Resolve denied IDs → names (stored as names for readability)
                        $deniedNames = Permission::whereIn('id', $removedIds)
                            ->pluck('name')
                            ->toArray();

                        // Save denied permissions on the user record
                        $record->denied_permissions = $deniedNames;
                        $record->save();

                        // Sync only the EXTRA direct permissions (not role defaults)
                        // Role permissions stay on the role; we don't duplicate them
                        $extraPermissions = Permission::whereIn('id', $extraIds)->get();
                        $record->syncPermissions($extraPermissions);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}