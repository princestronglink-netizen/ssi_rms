<?php

namespace App\Filament\Resources\UniformIssuances;

use App\Filament\Resources\UniformIssuances\Pages\CreateUniformIssuances;
use App\Filament\Resources\UniformIssuances\Pages\EditUniformIssuances;
use App\Filament\Resources\UniformIssuances\Pages\ListUniformIssuances;
use App\Filament\Resources\UniformIssuances\Schemas\UniformIssuancesForm;
use App\Filament\Resources\UniformIssuances\Tables\UniformIssuancesTable;
use App\Models\UniformIssuances;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class UniformIssuancesResource extends Resource
{
    protected static ?string $model = UniformIssuances::class;

    protected static BackedEnum|string|null $navigationIcon = 'fas-right-from-bracket';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('uniform_issuance_status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Distributions';
    }

    public static function form(Schema $schema): Schema
    {
        return UniformIssuancesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UniformIssuancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUniformIssuances::route('/'),
        ];
    }

    /**
     * Sync released_quantity / remaining_quantity for every item across
     * all recipients, based on the issuance status.
     *
     * Issuance type is now stored per-item (uniform_issuance_type_id on
     * uniform_issuance_items), so this method no longer touches the parent
     * record's type — it simply uses each item's own quantity.
     *
     * We re-query items fresh from the DB to avoid using stale in-memory
     * Eloquent collections after a previous update() call.
     */
    public static function syncQuantities($record): void
    {
        // Always reload relationships fresh to avoid stale cached collections
        $record->load('uniformIssuanceRecipient.uniformIssuanceItem');

        $status = $record->uniform_issuance_status;

        foreach ($record->uniformIssuanceRecipient as $recipient) {
            foreach ($recipient->uniformIssuanceItem as $item) {
                $qty = (int) $item->quantity;

                if ($status === 'issued') {
                    $item->update([
                        'released_quantity'  => $qty,
                        'remaining_quantity' => 0,
                    ]);
                } else {
                    // pending / partial — only set if not yet touched
                    // (don't overwrite partial releases already recorded)
                    $alreadyReleased = (int) $item->released_quantity;

                    if ($alreadyReleased === 0) {
                        $item->update([
                            'released_quantity'  => 0,
                            'remaining_quantity' => $qty,
                        ]);
                    }
                }
            }
        }
    }
}