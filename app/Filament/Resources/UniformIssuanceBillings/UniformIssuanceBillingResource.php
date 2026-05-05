<?php

namespace App\Filament\Resources\UniformIssuanceBillings;

use App\Filament\Resources\UniformIssuanceBillings\Pages\CreateUniformIssuanceBilling;
use App\Filament\Resources\UniformIssuanceBillings\Pages\EditUniformIssuanceBilling;
use App\Filament\Resources\UniformIssuanceBillings\Pages\ListUniformIssuanceBillings;
use App\Filament\Resources\UniformIssuanceBillings\Schemas\UniformIssuanceBillingForm;
use App\Filament\Resources\UniformIssuanceBillings\Tables\UniformIssuanceBillingsTable;
use App\Models\UniformIssuanceBilling;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UniformIssuanceBillingResource extends Resource
{
    protected static ?string $model = UniformIssuanceBilling::class;

    protected static BackedEnum|string|null $navigationIcon = 'fas-shirt';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger'; // red
    }
    
    public static function getNavigationGroup(): ?string
    {
        return 'Billing Management';
    }

    public static function form(Schema $schema): Schema
    {
        return UniformIssuanceBillingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UniformIssuanceBillingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUniformIssuanceBillings::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user && ! $user->isManager()) {
            $assignedClientIds = $user->assignedClients()->pluck('clients.id');

            $query->whereHas('uniformIssuance.site', function (Builder $q) use ($assignedClientIds) {
                $q->whereIn('client_id', $assignedClientIds);
            });
        }

        return $query;
    }
}
