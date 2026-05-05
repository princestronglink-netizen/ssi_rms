<?php
namespace App\Filament\Resources\Clients\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClientsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('client_name')
                    ->required(),
                TextInput::make('contact_person')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('contact_number')
                    ->required(),
                TextInput::make('address'),
                DatePicker::make('contract_start_date')
                    ->required(),
                DatePicker::make('contract_renewal_date')
                    ->required(),
                DatePicker::make('contract_end_date'),
                Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
                    ->required(),

                // ─── Payroll assignment ───────────────────────────────────
                Select::make('assignedUsers')
                    ->label('Assigned Payroll User(s)')
                    ->relationship('assignedUsers', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Select which payroll users can manage this client\'s billings.')
                    ->columnSpanFull(),
            ]);
    }
}