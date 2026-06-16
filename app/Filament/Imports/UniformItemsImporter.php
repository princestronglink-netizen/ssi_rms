<?php

namespace App\Filament\Imports;

use App\Models\UniformItems;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;

class UniformItemsImporter extends Importer
{
    protected static ?string $model = UniformItems::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('uniform_category_name')->rules(['nullable']),
            ImportColumn::make('uniform_item_name')->rules(['nullable']),
            ImportColumn::make('uniform_item_description')->rules(['nullable']),
            ImportColumn::make('uniform_item_price')->rules(['nullable']),
            ImportColumn::make('variants')->rules(['nullable']),
        ];
    }

    public static function getCsvDelimiter(): string { return "\t"; }
    public static function getCsvEnclosure(): string { return '"'; }

    // resolveRecord and saveRecord are effectively unused because our custom
    // job (ImportUniformItemsJob) handles all DB writes directly.
    // They must exist to satisfy the abstract contract.
    public function resolveRecord(): ?Model { return new UniformItems(); }
    public function saveRecord(): void {}

    public static function getCompletedNotificationBody(Import $import): string
    {
        $msg = "Import complete: {$import->successful_rows} item(s) processed.";
        if ($failed = $import->getFailedRowsCount()) {
            $msg .= " {$failed} row(s) failed — download the failure report for details.";
        }
        return $msg;
    }

    public function getJobConnection(): string { return 'sync'; }
    public function getJobQueue(): ?string     { return null; }
    public function getJobMiddleware(): array  { return []; }
}


// <?php

// namespace App\Filament\Imports;

// use App\Models\UniformItems;
// use App\Models\UniformItemVariants;
// use App\Models\UniformCategory;
// use Filament\Actions\Imports\Importer;
// use Filament\Actions\Imports\ImportColumn;
// use Filament\Actions\Imports\Models\Import;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Support\Facades\Log;

// class UniformItemsImporter extends Importer
// {
//     protected static ?string $model = UniformItems::class;

//     public static function getColumns(): array
//     {
//         return [
//             ImportColumn::make('uniform_category_name')
//                 ->rules(['required']),

//             ImportColumn::make('uniform_item_name')
//                 ->rules(['required']),

//             ImportColumn::make('uniform_item_description')
//                 ->rules(['nullable']),

//             ImportColumn::make('uniform_item_price')
//                 ->rules(['required', 'numeric']),

//             ImportColumn::make('variants')
//                 ->rules(['nullable']),
//         ];
//     }

//     public function resolveRecord(): ?Model
//     {
//         return new UniformItems();
//     }

//     public function saveRecord(): void
//     {
//         try {
//             Log::info('saveRecord DATA: ', $this->data);

//             // Normalize category name
//             $categoryName = trim(ucfirst(strtolower($this->data['uniform_category_name'])));

//             // Find or create category (case-insensitive)
//             $category = UniformCategory::whereRaw('LOWER(uniform_category_name) = ?', [strtolower($categoryName)])
//                 ->first();

//             if (!$category) {
//                 $category = UniformCategory::create([
//                     'uniform_category_name' => $categoryName,
//                 ]);
//             }

//             // Normalize item name
//             $itemName = trim(ucfirst(strtolower($this->data['uniform_item_name'])));

//             // Find existing item (case-insensitive)
//             $item = UniformItems::whereRaw('LOWER(uniform_item_name) = ?', [strtolower($itemName)])
//                 ->first();

//             if (!$item) {
//                 // Only create if it doesn't exist
//                 $item = UniformItems::create([
//                     'uniform_item_name'        => $itemName,
//                     'uniform_category_id'      => $category->id,
//                     'uniform_item_description' => $this->data['uniform_item_description'] ?? null,
//                     'uniform_item_price'       => $this->data['uniform_item_price'],
//                 ]);

//                 Log::info('Item created: ' . $item->id);
//             } else {
//                 Log::info('Item already exists, skipping: ' . $item->id);
//             }

//             // Only handle variants if item was just created
//             if (!empty($this->data['variants']) && $item->wasRecentlyCreated) {
//                 UniformItemVariants::where('uniform_item_id', $item->id)->delete();

//                 $variants = explode('|', $this->data['variants']);

//                 foreach ($variants as $variant) {
//                     $parts = explode(':', $variant);
//                     $size  = trim($parts[0] ?? '');
//                     $qty   = trim($parts[1] ?? 0);

//                     if ($size) {
//                         UniformItemVariants::create([
//                             'uniform_item_id'       => $item->id,
//                             'uniform_item_size'     => strtoupper($size),
//                             'uniform_item_quantity' => (int) $qty,
//                         ]);
//                     }
//                 }
//             }

//         } catch (\Throwable $e) {
//             Log::error('SAVE RECORD FAILED: ' . $e->getMessage(), [
//                 'row' => $this->data,
//             ]);
//             throw $e;
//         }
//     }

//     public static function getCompletedNotificationBody(Import $import): string
//     {
//         return "Imported {$import->successful_rows} items successfully.";
//     }

//     public function getJobQueue(): ?string
//     {
//         return null;
//     }

//     public function getJobConnection(): string
//     {
//         return 'sync';
//     }

//     public function getJobMiddleware(): array
//     {
//         return [];
//     }
// }