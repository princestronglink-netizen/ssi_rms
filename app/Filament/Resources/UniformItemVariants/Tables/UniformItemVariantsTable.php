<?php

namespace App\Filament\Resources\UniformItemVariants\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\ImageColumn;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;
use App\Models\UniformIssuanceLog;
use App\Models\UniformRestockLogs;
use App\Models\ReturnUniformItemLog;

class UniformItemVariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('uniformItem.uniform_item_image')
                    ->circular(),
                TextColumn::make('uniformItem.uniform_item_name')
                    ->searchable(),
                TextColumn::make('uniform_item_size')
                    ->searchable(),
                TextColumn::make('uniform_item_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('moq')
                    ->label('MOQ')
                    ->badge()
                    ->color(fn ($record) =>
                        $record->uniform_item_quantity == 0
                            ? 'danger'
                            : ($record->uniform_item_quantity <= $record->moq
                                ? 'warning'
                                : 'success')
                    )
                    ->formatStateUsing(fn ($record) =>
                        $record->moq
                    )
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->badge()
                    ->color(fn ($record) =>
                        $record->uniform_item_quantity == 0
                            ? 'danger'
                            : ($record->uniform_item_quantity <= $record->moq
                                ? 'warning'
                                : 'success')
                    )
                    ->getStateUsing(fn ($record) =>
                        $record->uniform_item_quantity == 0
                            ? 'No Stock'
                            : ($record->uniform_item_quantity <= $record->moq
                                ? 'Low Stock'
                                : 'Enough Stock')
                    ),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                
                // ─── STOCK LOGS ────────────────────────────────────────────
                Action::make('stock_logs')
                    ->label('Logs')
                    ->color('gray')
                    ->icon('heroicon-o-clock')
                    ->modalWidth('3xl')
                    ->modalHeading(fn ($record) =>
                        'Stock Logs — '
                        . ($record->uniformItem?->uniform_item_name ?? '—')
                        . ' ('
                        . ($record->uniform_item_size ?? '—')
                        . ')'
                    )
                    ->modalContent(function ($record) {

                        $variantId   = $record->id;
                        $itemName    = $record->uniformItem?->uniform_item_name ?? '—';
                        $size        = $record->uniform_item_size ?? '—';
                        $labelNeedle = "{$itemName} ({$size})";  // used to match inside note rows

                        // ── Collect matching entries from all three log tables ──
                        $allEntries = collect();

                        // ── 1. ISSUANCE LOGS ──────────────────────────────────
                        $issuanceLogs = UniformIssuanceLog::with('user')
                            ->whereIn('action', ['issued', 'partial', 'item_released', 'item_changed'])
                            ->get();

                        foreach ($issuanceLogs as $log) {
                            $noteData = json_decode($log->note ?? '[]', true);
                            if (!is_array($noteData)) continue;

                            foreach ($noteData as $row) {
                                // item_changed has two variants affected per row (old + new)
                                if ($log->action === 'item_changed') {
                                    // Check if the OLD variant matches (stock was returned)
                                    $oldVariantId = $row['_old_variant_id'] ?? null;
                                    if ($oldVariantId == $variantId) {
                                        $allEntries->push([
                                            'source'      => 'issuance',
                                            'action'      => 'item_changed_return',
                                            'label'       => e($row['_from'] ?? '—') . ' → returned to stock',
                                            'context'     => 'Employee: ' . e($row['_employee'] ?? '—'),
                                            'qty'         => (int) ($row['_change_qty'] ?? 0),
                                            'direction'   => '+',
                                            'stock_before'=> $row['old_stock_before'] ?? null,
                                            'stock_after' => $row['old_stock_after']  ?? null,
                                            'user'        => $log->user?->name ?? 'System',
                                            'date'        => $log->created_at,
                                            'reference'   => 'Issuance #' . $log->uniform_issuance_id,
                                        ]);
                                    }

                                    // Check if the NEW variant matches (stock was deducted)
                                    $toVariantId = $row['to_variant_id']
                                        ?? (\App\Models\UniformItemVariants::where('uniform_item_id', $row['to_item_id'] ?? 0)
                                                ->where('uniform_item_size', $row['_new_item_size'] ?? '')
                                                ->value('id'));

                                    // Simpler: match by label if to_variant_id not stored
                                    $newLabel = ($row['_new_item_name'] ?? '') . ' (' . ($row['_new_item_size'] ?? '') . ')';
                                    if (strcasecmp(trim($newLabel), trim($labelNeedle)) === 0) {
                                        $allEntries->push([
                                            'source'      => 'issuance',
                                            'action'      => 'item_changed_issue',
                                            'label'       => e($row['_to'] ?? '—') . ' → issued as replacement',
                                            'context'     => 'Employee: ' . e($row['_employee'] ?? '—'),
                                            'qty'         => (int) ($row['released'] ?? 0),
                                            'direction'   => '-',
                                            'stock_before'=> $row['new_stock_before'] ?? null,
                                            'stock_after' => $row['new_stock_after']  ?? null,
                                            'user'        => $log->user?->name ?? 'System',
                                            'date'        => $log->created_at,
                                            'reference'   => 'Issuance #' . $log->uniform_issuance_id,
                                        ]);
                                    }

                                } else {
                                    // issued / partial / item_released — match by label
                                    $rowLabel = $row['label'] ?? '';
                                    if (str_contains($rowLabel, $itemName) && str_contains($rowLabel, $size)) {
                                        $qty = (int) ($row['released'] ?? 0);
                                        if ($qty <= 0) continue;
                                        $allEntries->push([
                                            'source'       => 'issuance',
                                            'action'       => $log->action,
                                            'label'        => e($rowLabel),
                                            'context'      => null,
                                            'qty'          => $qty,
                                            'direction'    => '-',
                                            'stock_before' => isset($row['stock_before']) ? (int) $row['stock_before'] : null,
                                            'stock_after'  => isset($row['stock_after'])  ? (int) $row['stock_after']  : null,
                                            'user'         => $log->user?->name ?? 'System',
                                            'date'         => $log->created_at,
                                            'reference'    => 'Issuance #' . $log->uniform_issuance_id,
                                        ]);
                                    }
                                }
                            }
                        }

                        // ── 2. RESTOCK LOGS ───────────────────────────────────
                        $restockLogs = UniformRestockLogs::with('user')
                            ->whereIn('action', ['delivered', 'partial', 'returned'])
                            ->get();

                        foreach ($restockLogs as $log) {
                            $noteData = json_decode($log->note ?? '[]', true);
                            if (!is_array($noteData)) continue;

                            foreach ($noteData as $row) {
                                $rowLabel = $row['label'] ?? '';
                                if (!(str_contains($rowLabel, $itemName) && str_contains($rowLabel, $size))) continue;

                                if ($log->action === 'returned') {
                                    // Restock return — stock was deducted
                                    $qty = (int) ($row['qty'] ?? 0);
                                    if ($qty <= 0) continue;
                                    $allEntries->push([
                                        'source'       => 'restock',
                                        'action'       => 'restock_returned',
                                        'label'        => e($rowLabel),
                                        'context'      => 'Reason: ' . e(ucfirst(str_replace('_', ' ', $row['reason'] ?? '—'))),
                                        'qty'          => $qty,
                                        'direction'    => '-',
                                        'stock_before' => isset($row['stock_before']) ? (int) $row['stock_before'] : null,
                                        'stock_after'  => isset($row['stock_after'])  ? (int) $row['stock_after']  : null,
                                        'user'         => $log->user?->name ?? 'System',
                                        'date'         => $log->created_at,
                                        'reference'    => 'Restock #' . $log->uniform_restock_id,
                                    ]);
                                } else {
                                    // delivered / partial — stock was added
                                    $qty = (int) ($row['delivered'] ?? 0);
                                    if ($qty <= 0) continue;
                                    $allEntries->push([
                                        'source'       => 'restock',
                                        'action'       => $log->action === 'delivered' ? 'restock_delivered' : 'restock_partial',
                                        'label'        => e($rowLabel),
                                        'context'      => null,
                                        'qty'          => $qty,
                                        'direction'    => '+',
                                        'stock_before' => isset($row['stock_before']) ? (int) $row['stock_before'] : null,
                                        'stock_after'  => isset($row['stock_after'])  ? (int) $row['stock_after']  : null,
                                        'user'         => $log->user?->name ?? 'System',
                                        'date'         => $log->created_at,
                                        'reference'    => 'Restock #' . $log->uniform_restock_id,
                                    ]);
                                }
                            }
                        }

                        // ── 3. RETURN UNIFORM ITEM LOGS ───────────────────────
                        $returnLogs = ReturnUniformItemLog::with('user')
                            ->whereIn('action', ['returned', 'partial'])
                            ->get();

                        foreach ($returnLogs as $log) {
                            $noteData = json_decode($log->note ?? '[]', true);
                            if (!is_array($noteData)) continue;

                            foreach ($noteData as $row) {
                                $rowLabel = $row['label'] ?? '';
                                if (!(str_contains($rowLabel, $itemName) && str_contains($rowLabel, $size))) continue;

                                $qty        = (int) ($row['accepted'] ?? 0);
                                $addToStock = (bool) ($row['add_to_stock'] ?? false);
                                if ($qty <= 0) continue;

                                // Only include entries that actually affected stock
                                if (!$addToStock) {
                                    // Still show as a log entry, but mark clearly
                                    $allEntries->push([
                                        'source'       => 'return',
                                        'action'       => 'return_no_stock',
                                        'label'        => e($rowLabel),
                                        'context'      => 'No stock update (disposal/record only)',
                                        'qty'          => $qty,
                                        'direction'    => '0',
                                        'stock_before' => null,
                                        'stock_after'  => null,
                                        'user'         => $log->user?->name ?? 'System',
                                        'date'         => $log->created_at,
                                        'reference'    => 'Return #' . $log->return_uniform_item_id,
                                    ]);
                                } else {
                                    $allEntries->push([
                                        'source'       => 'return',
                                        'action'       => 'return_accepted',
                                        'label'        => e($rowLabel),
                                        'context'      => null,
                                        'qty'          => $qty,
                                        'direction'    => '+',
                                        'stock_before' => isset($row['stock_before']) ? (int) $row['stock_before'] : null,
                                        'stock_after'  => isset($row['stock_after'])  ? (int) $row['stock_after']  : null,
                                        'user'         => $log->user?->name ?? 'System',
                                        'date'         => $log->created_at,
                                        'reference'    => 'Return #' . $log->return_uniform_item_id,
                                    ]);
                                }
                            }
                        }

                        // ── Sort all entries newest first ──────────────────────
                        $allEntries = $allEntries->sortByDesc('date')->values();

                        if ($allEntries->isEmpty()) {
                            return new HtmlString("
                                <div style='padding:40px;text-align:center;color:#9ca3af;font-size:13px;'>
                                    No stock movement logs found for this variant.
                                </div>
                            ");
                        }

                        // ── Build rows ────────────────────────────────────────
                        $rows = '';
                        foreach ($allEntries as $entry) {
                            $date      = \Carbon\Carbon::parse($entry['date'])->timezone('Asia/Manila')->format('M d, Y h:i A');
                            $user      = e($entry['user']);
                            $reference = e($entry['reference']);
                            $label     = $entry['label'];
                            $context   = $entry['context'] ? e($entry['context']) : null;
                            $qty       = $entry['qty'];
                            $direction = $entry['direction'];
                            $action    = $entry['action'];
                            $source    = $entry['source'];

                            // ── Source badge ──
                            [$sourceBg, $sourceLabel] = match ($source) {
                                'issuance' => ['#1d4ed8', 'ISSUANCE'],
                                'restock'  => ['#16a34a', 'RESTOCK'],
                                'return'   => ['#7c3aed', 'RETURN'],
                                default    => ['#6b7280', strtoupper($source)],
                            };

                            // ── Action badge ──
                            [$actionBg, $actionLabel] = match ($action) {
                                'issued'               => ['#0d9488', 'ISSUED'],
                                'partial'              => ['#d97706', 'PARTIAL'],
                                'item_released'        => ['#0891b2', 'RELEASED'],
                                'item_changed_return'  => ['#16a34a', 'CHANGE RETURN'],
                                'item_changed_issue'   => ['#2563eb', 'CHANGE ISSUE'],
                                'restock_delivered'    => ['#16a34a', 'DELIVERED'],
                                'restock_partial'      => ['#d97706', 'PART. DELIVERY'],
                                'restock_returned'     => ['#dc2626', 'RETURNED'],
                                'return_accepted'      => ['#7c3aed', 'ACCEPTED'],
                                'return_no_stock'      => ['#9ca3af', 'NO STOCK CHG'],
                                default                => ['#6b7280', strtoupper(str_replace('_', ' ', $action))],
                            };

                            // ── Direction indicator & stock movement ──
                            if ($direction === '+') {
                                $dirColor  = '#16a34a';
                                $dirSymbol = "+{$qty}";
                                $dirBg     = '#f0fdf4';
                            } elseif ($direction === '-') {
                                $dirColor  = '#dc2626';
                                $dirSymbol = "−{$qty}";
                                $dirBg     = '#fef2f2';
                            } else {
                                $dirColor  = '#9ca3af';
                                $dirSymbol = "±0";
                                $dirBg     = '#f9fafb';
                            }

                            // ── Stock before/after ──
                            $stockHtml = '';
                            if ($entry['stock_before'] !== null && $entry['stock_after'] !== null) {
                                $arrowColor = $direction === '+' ? '#16a34a' : ($direction === '-' ? '#dc2626' : '#9ca3af');
                                $stockHtml = "
                                    <div style='font-size:10.5px;color:#9ca3af;margin-top:3px;'>
                                        stock:
                                        <span style='color:#374151;font-weight:600;'>{$entry['stock_before']}</span>
                                        →
                                        <span style='color:{$arrowColor};font-weight:700;'>{$entry['stock_after']}</span>
                                    </div>";
                            }

                            // ── Context line ──
                            $contextHtml = $context
                                ? "<div style='font-size:10.5px;color:#9ca3af;margin-top:2px;font-style:italic;'>{$context}</div>"
                                : '';

                            $rows .= "
                                <tr>
                                    <!-- Date / User / Reference -->
                                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top;min-width:130px;'>
                                        <div style='font-size:11px;color:#374151;white-space:nowrap;'>{$date}</div>
                                        <div style='font-size:10px;color:#9ca3af;margin-top:2px;'>{$user}</div>
                                        <div style='font-size:10px;color:#6b7280;margin-top:3px;
                                            background:#f1f5f9;padding:2px 7px;border-radius:999px;display:inline-block;'>
                                            {$reference}
                                        </div>
                                    </td>

                                    <!-- Source + Action badges -->
                                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top;white-space:nowrap;'>
                                        <div>
                                            <span style='background:{$sourceBg};color:#fff;font-size:9px;font-weight:700;
                                                padding:2px 8px;border-radius:999px;letter-spacing:.04em;'>
                                                {$sourceLabel}
                                            </span>
                                        </div>
                                        <div style='margin-top:4px;'>
                                            <span style='background:{$actionBg};color:#fff;font-size:9px;font-weight:700;
                                                padding:2px 8px;border-radius:999px;letter-spacing:.04em;'>
                                                {$actionLabel}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Label / context / stock movement -->
                                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top;'>
                                        <div style='font-size:11.5px;color:#111827;font-weight:500;'>{$label}</div>
                                        {$contextHtml}
                                        {$stockHtml}
                                    </td>

                                    <!-- Qty direction indicator -->
                                    <td style='padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:top;text-align:center;'>
                                        <div style='display:inline-flex;align-items:center;justify-content:center;
                                            background:{$dirBg};color:{$dirColor};font-size:13px;font-weight:800;
                                            min-width:52px;padding:4px 10px;border-radius:8px;letter-spacing:-.02em;'>
                                            {$dirSymbol}
                                        </div>
                                    </td>
                                </tr>";
                        }

                        // ── Legend ────────────────────────────────────────────
                        $legendItems = [
                            ['bg' => '#1d4ed8', 'label' => 'Issuance'],
                            ['bg' => '#16a34a', 'label' => 'Restock'],
                            ['bg' => '#7c3aed', 'label' => 'Return'],
                        ];
                        $legendHtml = '';
                        foreach ($legendItems as $li) {
                            $legendHtml .= "
                                <span style='display:inline-flex;align-items:center;gap:5px;margin-right:12px;'>
                                    <span style='width:8px;height:8px;border-radius:50%;background:{$li['bg']};display:inline-block;'></span>
                                    <span style='font-size:11px;color:#6b7280;'>{$li['label']}</span>
                                </span>";
                        }

                        $currentStock = (int) $record->uniform_item_quantity;
                        $totalCount   = $allEntries->count();

                        return new HtmlString("
                            <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>

                                <!-- Summary strip -->
                                <div style='display:flex;justify-content:space-between;align-items:center;
                                    padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;
                                    border-radius:10px;margin-bottom:12px;'>
                                    <div>
                                        <div style='font-size:12px;font-weight:700;color:#1e3a5f;'>
                                            {$itemName} · <span style='color:#6b7280;font-weight:400;'>{$size}</span>
                                        </div>
                                        <div style='font-size:11px;color:#9ca3af;margin-top:2px;'>{$totalCount} log entr" . ($totalCount === 1 ? 'y' : 'ies') . "</div>
                                    </div>
                                    <div style='text-align:right;'>
                                        <div style='font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;'>Current Stock</div>
                                        <div style='font-size:20px;font-weight:900;color:#1e3a5f;letter-spacing:-.03em;'>{$currentStock}</div>
                                    </div>
                                </div>

                                <!-- Legend -->
                                <div style='padding:6px 0 10px;'>{$legendHtml}</div>

                                <!-- Table -->
                                <div style='max-height:520px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:10px;'>
                                    <table style='width:100%;border-collapse:collapse;'>
                                        <thead style='position:sticky;top:0;z-index:1;'>
                                            <tr style='background:#1e3a5f;'>
                                                <th style='padding:9px 12px;text-align:left;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;'>Date / By</th>
                                                <th style='padding:9px 12px;text-align:left;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.06em;'>Source</th>
                                                <th style='padding:9px 12px;text-align:left;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.06em;'>Details</th>
                                                <th style='padding:9px 12px;text-align:center;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.06em;width:70px;'>Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                </div>

                            </div>
                        ");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                    // ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}