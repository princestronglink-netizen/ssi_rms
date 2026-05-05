<?php

namespace App\Filament\Imports;

use App\Models\OfficeSupplyItem;
use App\Models\OfficeSupplyItemVariant;
use App\Models\OfficeSupplyCategory;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class OfficeSupplyItemsImporter extends Importer
{
    protected static ?string $model = OfficeSupplyItem::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('office_supply_category_name')
                ->rules(['required']),

            ImportColumn::make('office_supply_name')
                ->rules(['required']),

            ImportColumn::make('office_supply_description')
                ->rules(['nullable']),

            ImportColumn::make('office_supply_price')
                ->rules(['required', 'numeric']),

            ImportColumn::make('variants')
                ->rules(['nullable']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return new OfficeSupplyItem();
    }

    public function saveRecord(): void
    {
        try {
            Log::info('OfficeSupply saveRecord DATA: ', $this->data);

            // Normalize category name
            $categoryName = trim(ucfirst(strtolower($this->data['office_supply_category_name'])));

            // Find or create category (case-insensitive)
            $category = OfficeSupplyCategory::whereRaw('LOWER(office_supply_category_name) = ?', [strtolower($categoryName)])
                ->first();

            if (!$category) {
                $category = OfficeSupplyCategory::create([
                    'office_supply_category_name' => $categoryName,
                ]);
            }

            // Normalize item name
            $itemName = trim(ucfirst(strtolower($this->data['office_supply_name'])));

            // Find existing item (case-insensitive)
            $item = OfficeSupplyItem::whereRaw('LOWER(office_supply_name) = ?', [strtolower($itemName)])
                ->first();

            if (!$item) {
                // Only create if it doesn't exist
                $item = OfficeSupplyItem::create([
                    'office_supply_name'        => $itemName,
                    'office_supply_category_id' => $category->id,
                    'office_supply_description' => $this->data['office_supply_description'] ?? null,
                    'office_supply_price'       => $this->data['office_supply_price'],
                ]);

                Log::info('OfficeSupply Item created: ' . $item->id);
            } else {
                Log::info('OfficeSupply Item already exists, skipping: ' . $item->id);
            }

            // Only handle variants if item was just created
            if (!empty($this->data['variants']) && $item->wasRecentlyCreated) {
                OfficeSupplyItemVariant::where('office_supply_item_id', $item->id)->delete();

                $variants = explode('|', $this->data['variants']);

                foreach ($variants as $variant) {
                    $parts       = explode(':', $variant);
                    $variantName = trim(ucfirst(strtolower($parts[0] ?? '')));
                    $qty         = trim($parts[1] ?? 0);

                    if ($variantName) {
                        OfficeSupplyItemVariant::create([
                            'office_supply_item_id'  => $item->id,
                            'office_supply_variant'  => $variantName,
                            'office_supply_quantity' => (int) $qty,
                        ]);
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('OfficeSupply SAVE RECORD FAILED: ' . $e->getMessage(), [
                'row' => $this->data,
            ]);
            throw $e;
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return "Imported {$import->successful_rows} office supply items successfully.";
    }

    public function getJobQueue(): ?string
    {
        return null;
    }

    public function getJobConnection(): string
    {
        return 'sync';
    }

    public function getJobMiddleware(): array
    {
        return [];
    }
}