<?php

namespace App\Jobs;

use App\Models\UniformItems;
use App\Models\UniformItemVariants;
use App\Models\UniformCategory;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WHY THIS JOB EXISTS
 * -------------------
 * Filament's default ImportCsv job uses League\Csv (fgetcsv-based) to parse
 * the uploaded file into a $rows payload that gets serialized into the job.
 * League\Csv breaks on item names containing parentheses (), slashes /, etc.
 * because those characters interfere with its quoting/enclosure logic.
 *
 * This job is a STANDALONE replacement. It:
 *   1. Ignores the $rows payload entirely.
 *   2. Re-opens the file directly from storage.
 *   3. Reads it with fgets() + explode("\t") — zero special-char issues.
 *   4. Updates Filament's import counters and fires the completion event.
 *
 * WIRING (in your Resource or ListPage):
 *
 *   ImportAction::make()
 *       ->importer(UniformItemsImporter::class)
 *       ->job(ImportUniformItemsJob::class)
 */
class ImportUniformItemsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    // These constructor parameters MUST match Filament's ImportCsv signature
    // exactly so the job can be dispatched as a drop-in replacement.
    // $rows and $columnMap are intentionally ignored — we re-read the file.
    public function __construct(
        public Import        $import,
        public array|string  $rows,       // ignored — file re-read directly
        public array         $columnMap,  // ignored
        public array         $options = [],
    ) {}

    public function handle(): void
    {
        // ── Auth (mirrors Filament's own ImportCsv job) ───────────────────
        $user = $this->import->user;
        if ($user && method_exists(auth()->guard(), 'login')) {
            auth()->login($user);
        } elseif ($user) {
            auth()->setUser($user);
        }

        try {
            $this->runImport();
        } finally {
            auth()->forgetGuards();
        }
    }

    private function runImport(): void
    {
        // ── Locate uploaded file ──────────────────────────────────────────
        $filePath = storage_path('app/' . $this->import->file_path);
        if (!file_exists($filePath)) {
            $filePath = storage_path('app/public/' . $this->import->file_path);
        }
        if (!file_exists($filePath)) {
            Log::error('[UniformImport] File not found: ' . $filePath);
            return;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            Log::error('[UniformImport] Cannot open: ' . $filePath);
            return;
        }

        $columns = [
            'uniform_category_name',
            'uniform_item_name',
            'uniform_item_description',
            'uniform_item_price',
            'variants',
        ];

        $lineNumber     = 0;
        $processedRows  = 0;
        $successfulRows = 0;
        $exceptions     = [];

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;

            // First line: strip BOM + skip header
            if ($lineNumber === 1) {
                $line = preg_replace('/^(\xef\xbb\xbf|\xfe\xff|\xff\xfe)/', '', $line);
                continue;
            }

            $line = rtrim($line, "\r\n");
            if (trim($line) === '') continue;

            // Split ONLY on tab — (), /, -, ', spaces all pass through untouched
            $fields = explode("\t", $line);
            while (count($fields) < count($columns)) {
                $fields[] = '';
            }

            $row = [];
            foreach ($columns as $i => $col) {
                $row[$col] = trim($fields[$i] ?? '');
            }

            $processedRows++;
            Log::info("[UniformImport] Line {$lineNumber}:", $row);

            try {
                DB::transaction(function () use ($row) {
                    $this->processRow($row);
                });
                $successfulRows++;
            } catch (\Throwable $e) {
                Log::error('[UniformImport] Row failed: ' . $e->getMessage(), ['row' => $row]);
                $exceptions[$e::class] = $e;

                // Record in Filament's failed_import_rows table
                $failedRow = app(FailedImportRow::class);
                $failedRow->import()->associate($this->import);
                $failedRow->data             = $row;
                $failedRow->validation_error = $e->getMessage();
                $failedRow->save();
            }
        }

        fclose($handle);

        // ── Update Filament's import counters ─────────────────────────────
        $this->import::query()
            ->whereKey($this->import)
            ->update([
                'processed_rows'  => DB::raw("processed_rows + {$processedRows}"),
                'successful_rows' => DB::raw("successful_rows + {$successfulRows}"),
            ]);

        $this->import->refresh();

        // ── Fire event so Filament sends the completion notification ──────
        event(new ImportChunkProcessed(
            $this->import,
            $this->columnMap,
            $this->options,
            $processedRows,
            $successfulRows,
            $exceptions,
        ));

        Log::info("[UniformImport] Done — {$processedRows} processed, {$successfulRows} ok.");
    }

    private function processRow(array $data): void
    {
        $data = $this->sanitize($data);

        // ── Category ─────────────────────────────────────────────────────
        $categoryName = $data['uniform_category_name'] ?? '';
        if ($categoryName === '') return;

        $category = UniformCategory::whereRaw(
            'LOWER(TRIM(uniform_category_name)) = LOWER(TRIM(?))',
            [$categoryName]
        )->first() ?? UniformCategory::create(['uniform_category_name' => $categoryName]);

        // ── Item ─────────────────────────────────────────────────────────
        $itemName = $data['uniform_item_name'] ?? '';
        if ($itemName === '') return;

        // Use LOWER() on both sides in MySQL — this is safe because we want
        // case-insensitive match, and we do NOT use BINARY here so collation
        // differences don't matter. The key fix vs previous attempts:
        // we use a direct targeted query instead of loading ALL items.
        $exists = UniformItems::whereRaw(
            'LOWER(TRIM(uniform_item_name)) = LOWER(TRIM(?))',
            [trim($itemName)]
        )->exists();

        if ($exists) {
            Log::info("[UniformImport] Duplicate, skipping: '{$itemName}'");
            return;
        }

        $item = UniformItems::create([
            'uniform_item_name'        => $itemName,
            'uniform_category_id'      => $category->id,
            'uniform_item_description' => $data['uniform_item_description'] ?? null,
            'uniform_item_price'       => is_numeric($data['uniform_item_price'] ?? '')
                ? (float) $data['uniform_item_price'] : 0.0,
        ]);

        Log::info("[UniformImport] Created item {$item->id}: {$itemName}");

        $variantsRaw = $data['variants'] ?? '';
        if ($variantsRaw !== '') {
            $this->createVariants($item->id, $variantsRaw);
        }
    }

    private function createVariants(int $itemId, string $variantsRaw): void
    {
        UniformItemVariants::where('uniform_item_id', $itemId)->delete();

        foreach (explode('|', $variantsRaw) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;

            $lastColon = strrpos($chunk, ':');
            if ($lastColon === false) continue;

            $size = strtoupper(trim(substr($chunk, 0, $lastColon)));
            $qty  = (int) trim(substr($chunk, $lastColon + 1));
            if ($size === '') continue;

            UniformItemVariants::create([
                'uniform_item_id'       => $itemId,
                'uniform_item_size'     => $size,
                'uniform_item_quantity' => $qty,
            ]);
        }
    }

    private function sanitize(array $data): array
    {
        return array_map(function ($value) {
            if (!is_string($value)) return $value;
            $value = preg_replace('/^(\xef\xbb\xbf|\xfe\xff|\xff\xfe)/', '', $value);
            $value = str_replace("\xc2\xa0", ' ', $value);
            $value = preg_replace('/[\x{00AD}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $value);
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            return trim($value);
        }, $data);
    }
}