<?php

namespace App\Filament\Resources\SmeBillings;

use App\Filament\Resources\SmeBillings\Pages\CreateSmeBilling;
use App\Filament\Resources\SmeBillings\Pages\EditSmeBilling;
use App\Filament\Resources\SmeBillings\Pages\ListSmeBillings;
use App\Filament\Resources\SmeBillings\Schemas\SmeBillingForm;
use App\Filament\Resources\SmeBillings\Tables\SmeBillingsTable;
use App\Models\SmeBilling;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SmeBillingResource extends Resource
{
    protected static ?string $model = SmeBilling::class;

    protected static BackedEnum|string|null $navigationIcon = 'fas-boxes-stacked';

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
        return SmeBillingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SmeBillingsTable::configure($table);
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
            'index' => ListSmeBillings::route('/'),
        ];
    }


    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if ($user && ! $user->isManager()) {
            $assignedClientIds = $user->assignedClients()->pluck('clients.id');

            $query->whereHas('purchaseOrder.site', function (Builder $q) use ($assignedClientIds) {
                $q->whereIn('client_id', $assignedClientIds);
            });
        }

        return $query;
    }
}
