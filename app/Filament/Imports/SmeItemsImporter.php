<?php

namespace App\Filament\Imports;

use App\Models\SmeItems;
use App\Models\SmeItemVariants;
use App\Models\SmeCategory;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SmeItemsImporter extends Importer
{
    protected static ?string $model = SmeItems::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sme_category_name')
                ->rules(['required']),

            ImportColumn::make('sme_item_name')
                ->rules(['required']),

            ImportColumn::make('sme_item_brand')
                ->rules(['required']),

            ImportColumn::make('sme_item_description')
                ->rules(['nullable']),

            ImportColumn::make('sme_item_price')
                ->rules(['required', 'numeric']),

            ImportColumn::make('variants')
                ->rules(['nullable']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        return new SmeItems();
    }

    public function saveRecord(): void
    {
        try {
            Log::info('SME saveRecord DATA: ', $this->data);

            // Normalize category name
            $categoryName = trim(ucfirst(strtolower($this->data['sme_category_name'])));

            // Find or create category (case-insensitive)
            $category = SmeCategory::whereRaw('LOWER(sme_category_name) = ?', [strtolower($categoryName)])
                ->first();

            if (!$category) {
                $category = SmeCategory::create([
                    'sme_category_name' => $categoryName,
                ]);
            }

            // Normalize item name and brand
            $itemName  = trim(ucfirst(strtolower($this->data['sme_item_name'])));
            $itemBrand = trim(ucfirst(strtolower($this->data['sme_item_brand'])));

            // Find existing item (case-insensitive)
            $item = SmeItems::whereRaw('LOWER(sme_item_name) = ?', [strtolower($itemName)])
                ->first();

            if (!$item) {
                // Only create if it doesn't exist
                $item = SmeItems::create([
                    'sme_item_name'        => $itemName,
                    'sme_category_id'      => $category->id,
                    'sme_item_brand'       => $itemBrand,
                    'sme_item_description' => $this->data['sme_item_description'] ?? null,
                    'sme_item_price'       => $this->data['sme_item_price'],
                ]);

                Log::info('SME Item created: ' . $item->id);
            } else {
                Log::info('SME Item already exists, skipping: ' . $item->id);
            }

            // Only handle variants if item was just created
            if (!empty($this->data['variants']) && $item->wasRecentlyCreated) {
                SmeItemVariants::where('sme_item_id', $item->id)->delete();

                $variants = explode('|', $this->data['variants']);

                foreach ($variants as $variant) {
                    $parts = explode(':', $variant);
                    $size  = trim($parts[0] ?? '');
                    $qty   = trim($parts[1] ?? 0);

                    if ($size) {
                        SmeItemVariants::create([
                            'sme_item_id'       => $item->id,
                            'sme_item_size'     => strtoupper($size),
                            'sme_item_quantity' => (int) $qty,
                        ]);
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('SME SAVE RECORD FAILED: ' . $e->getMessage(), [
                'row' => $this->data,
            ]);
            throw $e;
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return "Imported {$import->successful_rows} SME items successfully.";
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