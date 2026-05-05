<?php

namespace App\Filament\Resources\Billings;

use App\Filament\Resources\Billings\Pages\ListBillings;
use App\Filament\Resources\Billings\Schemas\BillingForm;
use App\Filament\Resources\Billings\Tables\BillingsTable;
use App\Models\Billing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BillingResource extends Resource
{
    protected static ?string $model = Billing::class;

    protected static BackedEnum|string|null $navigationIcon = 'fas-file-invoice';

    /**
     * Scope the Eloquent query so payroll users only see
     * billings that belong to their assigned clients.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user && ! $user->isManager()) {
            // Only billings whose client_id is in the user's assigned clients
            $assignedClientIds = $user->assignedClients()->pluck('clients.id');
            $query->whereIn('client_id', $assignedClientIds);
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        // Badge respects the scoped query automatically
        $count = static::getEloquentQuery()->where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Billing Management';
    }

    public static function form(Schema $schema): Schema
    {
        return BillingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillings::route('/'),
        ];
    }
}