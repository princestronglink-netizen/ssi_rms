<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get; // ← change this
use Filament\Schemas\Components\Utilities\Set; // ← change this
use Filament\Schemas\Schema;
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

                        $set('permissions', $rolePermissions);
                    }),

                Select::make('permissions')
                    ->multiple()
                    ->relationship('permissions', 'name')
                    ->preload()
                    ->searchable()
                    ->required(false)
                    ->helperText('Auto-loaded from selected role. You can add or remove.')
                    ->afterStateHydrated(function (Get $get, Set $set, $record) {
                        if (!$record) return;

                        $allPermissions = $record->getAllPermissions()
                            ->pluck('id')
                            ->toArray();

                        $set('permissions', $allPermissions);
                    }),
            ]);
    }
}