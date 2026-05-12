<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),

                DateTimePicker::make('email_verified_at'),

                TextInput::make('temporary_password')
                    ->label('Temporary Password')
                    ->default(fn () => \Illuminate\Support\Str::random(12))
                    ->required()
                    ->helperText('Auto-generated. You may change this before saving.')
                    ->hidden()
                    ->dehydrated(false),

                Select::make('department_id')
                    ->relationship('department', 'department_name')
                    ->preload()
                    ->searchable(),

                Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $selectedRoleIds = $get('roles');

                        if (empty($selectedRoleIds)) {
                            $set('permissions', []);
                            $set('role_permission_ids', []);
                            return;
                        }

                        $rolePermissions = Role::whereIn('id', $selectedRoleIds)
                            ->with('permissions')
                            ->get()
                            ->flatMap(fn($role) => $role->permissions)
                            ->pluck('id')
                            ->unique()
                            ->values()
                            ->toArray();

                        $set('role_permission_ids', $rolePermissions);
                        $set('permissions', $rolePermissions);
                    }),

                // Tracks what the role(s) grant — used to compute denied/added
                Hidden::make('role_permission_ids')
                    ->default([]),

                Select::make('permissions')
                    ->multiple()
                    ->options(fn () => Permission::all()->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->live()
                    ->helperText(function (Get $get): string {
                        $roleIds     = $get('role_permission_ids') ?? [];
                        $selectedIds = $get('permissions') ?? [];

                        $added   = array_diff($selectedIds, $roleIds);
                        $removed = array_diff($roleIds, $selectedIds);

                        $parts = ['Role permissions loaded as default. Add or remove freely.'];

                        if (! empty($added)) {
                            $names   = Permission::whereIn('id', $added)->pluck('name')->join(', ');
                            $parts[] = "➕ Added (extra): {$names}";
                        }

                        if (! empty($removed)) {
                            $names   = Permission::whereIn('id', $removed)->pluck('name')->join(', ');
                            $parts[] = "➖ Removed (denied from role): {$names}";
                        }

                        return implode("\n", $parts);
                    })
                    ->afterStateHydrated(function (Get $get, Set $set, $record) {
                        if (! $record) {
                            return;
                        }

                        // Role-granted permission IDs
                        $rolePermissionIds = $record->roles()
                            ->with('permissions')
                            ->get()
                            ->flatMap(fn($role) => $role->permissions)
                            ->pluck('id')
                            ->unique()
                            ->values()
                            ->toArray();

                        $set('role_permission_ids', $rolePermissionIds);

                        // Direct permissions explicitly granted on the user
                        $directPermissionIds = $record->permissions()
                            ->pluck('id')
                            ->toArray();

                        // Denied names → IDs so we can subtract from the display
                        $deniedNames = $record->denied_permissions ?? [];
                        $deniedIds   = Permission::whereIn('name', $deniedNames)
                            ->pluck('id')
                            ->toArray();

                        // What admin sees = (role permissions + direct extras) - denied
                        $effective = array_values(array_unique(array_merge($rolePermissionIds, $directPermissionIds)));
                        $effective = array_values(array_diff($effective, $deniedIds));

                        $set('permissions', $effective);
                    }),
            ]);
    }
}