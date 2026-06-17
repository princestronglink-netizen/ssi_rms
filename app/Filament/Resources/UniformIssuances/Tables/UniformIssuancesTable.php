<?php

namespace App\Filament\Resources\UniformIssuances\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Filament\Resources\UniformIssuances\UniformIssuancesResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use App\Models\UniformItemVariants;
use App\Models\UniformItems;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use App\Models\UniformIssuanceLog;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Actions\ActionGroup;
use App\Filament\Resources\UniformIssuances\Schemas\UniformIssuancesForm;
use Illuminate\Support\Facades\DB;

class UniformIssuancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.site_name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uniformIssuanceType.uniform_issuance_type_name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uniform_issuance_status')
                    ->badge()
                    ->colors([
                        'success' => 'issued',
                        'primary' => 'partial',
                        'warning' => 'pending',
                        'danger'  => 'cancelled',
                    ]),
                TextColumn::make('status_date')
                    ->label('Date')
                    ->date()
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderByRaw("
                            CASE uniform_issuance_status
                                WHEN 'pending'   THEN pending_at
                                WHEN 'partial'   THEN partial_at
                                WHEN 'issued'    THEN issued_at
                                WHEN 'cancelled' THEN cancelled_at
                                ELSE NULL
                            END {$direction}
                        ");
                    })
                    ->getStateUsing(fn ($record) => match($record->uniform_issuance_status) {
                        'pending'   => $record->pending_at,
                        'partial'   => $record->partial_at,
                        'issued'    => $record->issued_at,
                        'cancelled' => $record->cancelled_at,
                        default     => null,
                    }),
                TextColumn::make('signed_receiving_copy')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    // ─── VIEW: show employees with their items ────────────────
                    Action::make('view')
                        ->label('View')
                        ->color('gray')
                        ->icon('heroicon-o-eye')
                        ->modalWidth('3xl')
                        ->modalHeading(fn ($record) => 'Issuance — ' . ($record->site?->site_name ?? '—'))
                        ->modalContent(function ($record) {
                            $record->loadMissing(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                                'uniformIssuanceRecipient.position',
                                'site',
                                'uniformIssuanceType'
                            );

                            $siteName = e($record->site?->site_name ?? '—');
                            $typeName = e($record->uniformIssuanceType?->uniform_issuance_type_name ?? '—');

                            $statusColor = match ($record->uniform_issuance_status) {
                                'issued'    => '#16a34a',
                                'partial'   => '#d97706',
                                'pending'   => '#2563eb',
                                'cancelled' => '#dc2626',
                                default     => '#6b7280',
                            };
                            $statusLabel = strtoupper($record->uniform_issuance_status ?? 'PENDING');

                            $statusDate = match ($record->uniform_issuance_status) {
                                'pending'   => $record->pending_at,
                                'partial'   => $record->partial_at,
                                'issued'    => $record->issued_at,
                                'cancelled' => $record->cancelled_at,
                                default     => null,
                            };
                            $statusDateFormatted = $statusDate
                                ? \Carbon\Carbon::parse($statusDate)->format('F d, Y')
                                : '—';

                            // ── Per-recipient blocks ──────────────────────────────
                            $recipientBlocks = '';
                            $grandTotalQty       = 0;
                            $grandTotalReleased  = 0;
                            $grandTotalRemaining = 0;

                            foreach ($record->uniformIssuanceRecipient as $ri => $recipient) {
                                $employee       = e($recipient->employee_name ?? '—');
                                $position       = e($recipient->position?->position_name ?? '—');
                                $employeeStatus = strtolower($recipient->employee_status ?? '');

                                $statusBadgeHtml = '';
                                if ($employeeStatus) {
                                    $empStatusColor = match ($employeeStatus) {
                                        'regular'      => '#16a34a',
                                        'reliever'     => '#d97706',
                                        'probationary' => '#2563eb',
                                        'contractual'  => '#7c3aed',
                                        'posted'       => '#0d9488',
                                        default        => '#6b7280',
                                    };
                                    $statusBadgeHtml = "
                                        <span style='background:{$empStatusColor};color:#fff;font-size:9px;font-weight:700;
                                            padding:2px 8px;border-radius:999px;margin-left:6px;letter-spacing:.04em;'>
                                            " . strtoupper($employeeStatus) . "
                                        </span>";
                                }

                                $itemRows       = '';
                                $totalQty       = 0;
                                $totalReleased  = 0;
                                $totalRemaining = 0;

                                foreach ($recipient->uniformIssuanceItem as $i => $item) {
                                    $itemName  = e($item->uniformItem?->uniform_item_name ?? '—');
                                    $size      = e($item->uniformItemVariant?->uniform_item_size ?? '—');
                                    $qty       = (int) $item->quantity;
                                    $released  = (int) $item->released_quantity;
                                    $remaining = (int) $item->remaining_quantity;

                                    $totalQty       += $qty;
                                    $totalReleased  += $released;
                                    $totalRemaining += $remaining;

                                    $releasedColor  = $released  > 0 ? '#16a34a' : '#9ca3af';
                                    $remainingColor = $remaining > 0 ? '#d97706' : '#9ca3af';
                                    $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';

                                    $typeName = e($item->uniformIssuanceType?->uniform_issuance_type_name ?? '—');

                                    $itemRows .= "
                                        <tr style='background:{$bg};'>
                                            <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#111827;font-weight:500;'>{$itemName}</td>
                                            <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;color:#374151;text-align:center;'>{$size}</td>
                                            <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#7c3aed;text-align:center;'>{$typeName}</td>
                                            <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;text-align:center;color:#1d4ed8;'>{$qty}</td>
                                            <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;text-align:center;color:{$releasedColor};'>{$released}</td>
                                            <td style='padding:9px 14px;border-bottom:1px solid #e5e7eb;font-size:13px;font-weight:700;text-align:center;color:{$remainingColor};'>{$remaining}</td>
                                        </tr>";
                                }

                                // Totals footer
                                $itemRows .= "
                                    <tr style='background:#eff6ff;border-top:2px solid #93c5fd;'>
                                        <td colspan='2' style='padding:8px 14px;font-size:11px;font-weight:700;color:#374151;text-align:right;'>TOTAL</td>
                                        <td style='padding:8px 14px;font-size:14px;font-weight:900;color:#1d4ed8;text-align:center;'>{$totalQty}</td>
                                        <td style='padding:8px 14px;font-size:14px;font-weight:900;color:#16a34a;text-align:center;'>{$totalReleased}</td>
                                        <td style='padding:8px 14px;font-size:14px;font-weight:900;color:#d97706;text-align:center;'>{$totalRemaining}</td>
                                    </tr>";

                                $grandTotalQty       += $totalQty;
                                $grandTotalReleased  += $totalReleased;
                                $grandTotalRemaining += $totalRemaining;

                                $recipientBlocks .= "
                                    <div style='border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:12px;'>
                                        <div style='background:#1e3a5f;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;'>
                                            <div>
                                                <div style='font-size:13px;font-weight:700;color:#fff;display:flex;align-items:center;'>
                                                    {$employee}{$statusBadgeHtml}
                                                </div>
                                                <div style='font-size:11px;color:#93c5fd;margin-top:2px;'>{$position}</div>
                                            </div>
                                        </div>
                                        <table style='width:100%;border-collapse:collapse;'>
                                            <thead>
                                                <tr style='background:#f1f5f9;'>
                                                    <th style='padding:7px 14px;text-align:left;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;'>Item</th>
                                                    <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;width:60px;'>Size</th>
                                                    <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;'>Type</th>
                                                    <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;width:60px;'>Qty</th>
                                                    <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;width:75px;'>Released</th>
                                                    <th style='padding:7px 14px;text-align:center;font-size:10px;font-weight:700;color:#d97706;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e2e8f0;width:80px;'>Remaining</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$itemRows}</tbody>
                                        </table>
                                    </div>";
                            }

                            // ── Grand total bar ───────────────────────────────────
                            $grandTotalBar = "
                                <div style='border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:16px;'>
                                    <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                        Grand Total
                                    </div>
                                    <div style='display:grid;grid-template-columns:1fr 1fr 1fr;'>
                                        <div style='padding:12px 14px;text-align:center;border-right:1px solid #f1f5f9;'>
                                            <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;'>Total Qty</div>
                                            <div style='font-size:20px;font-weight:900;color:#1d4ed8;'>{$grandTotalQty}</div>
                                        </div>
                                        <div style='padding:12px 14px;text-align:center;border-right:1px solid #f1f5f9;'>
                                            <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;'>Released</div>
                                            <div style='font-size:20px;font-weight:900;color:#16a34a;'>{$grandTotalReleased}</div>
                                        </div>
                                        <div style='padding:12px 14px;text-align:center;'>
                                            <div style='font-size:10px;color:#6b7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;'>Remaining</div>
                                            <div style='font-size:20px;font-weight:900;color:#d97706;'>{$grandTotalRemaining}</div>
                                        </div>
                                    </div>
                                </div>";

                            // ── Linked transmittals ───────────────────────────────
                            $linkedTransmittalsHtml = '';
                            $transmittals = \App\Models\Transmittals::where('uniform_issuance_id', $record->id)->get();

                            if ($transmittals->count() > 0) {
                                $txRows = '';
                                foreach ($transmittals as $txn) {
                                    $txNum   = e($txn->transmittal_number);
                                    $txTo    = e($txn->transmitted_to);
                                    $txDate  = $txn->transmitted_at
                                        ? \Carbon\Carbon::parse($txn->transmitted_at)->format('M d, Y')
                                        : '—';
                                    $txStatusColor = match ($txn->status) {
                                        'received_from_office' => '#0284c7',
                                        'received_from_site'   => '#16a34a',
                                        'document_returned'    => '#6b7280',
                                        default                => '#d97706',
                                    };
                                    $txStatusLabel = match ($txn->status) {
                                        'pending'              => 'PENDING',
                                        'received_from_office' => 'RECV. OFFICE',
                                        'received_from_site'   => 'RECV. SITE',
                                        'document_returned'    => 'DOC RETURNED',
                                        default                => strtoupper($txn->status),
                                    };
                                    $txRows .= "
                                        <tr>
                                            <td style='padding:8px 14px;border-bottom:1px solid #e5e7eb;font-size:12px;font-weight:600;color:#111827;'>{$txNum}</td>
                                            <td style='padding:8px 14px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#374151;'>{$txTo}</td>
                                            <td style='padding:8px 14px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#374151;text-align:center;'>{$txDate}</td>
                                            <td style='padding:8px 14px;border-bottom:1px solid #e5e7eb;text-align:center;'>
                                                <span style='background:{$txStatusColor};color:#fff;font-size:9.5px;font-weight:700;
                                                    padding:2px 8px;border-radius:999px;letter-spacing:.04em;'>{$txStatusLabel}</span>
                                            </td>
                                        </tr>";
                                }

                                $count = $transmittals->count();
                                $linkedTransmittalsHtml = "
                                    <div style='margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                                        <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                            Transmittals ({$count})
                                        </div>
                                        <table style='width:100%;border-collapse:collapse;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:8px 14px;text-align:left;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>No.</th>
                                                    <th style='padding:8px 14px;text-align:left;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Transmitted To</th>
                                                    <th style='padding:8px 14px;text-align:center;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Date</th>
                                                    <th style='padding:8px 14px;text-align:center;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$txRows}</tbody>
                                        </table>
                                    </div>";
                            }

                            return new \Illuminate\Support\HtmlString("
                                <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>

                                    <!-- ── Header card ── -->
                                    <div style='background:linear-gradient(135deg,#1e3a5f 0%,#1e40af 100%);
                                        border-radius:12px;padding:18px 20px;margin-bottom:16px;'>
                                        <div style='display:flex;justify-content:space-between;align-items:flex-start;'>
                                            <div>
                                                <div style='font-size:18px;font-weight:800;color:#fff;letter-spacing:-0.02em;'>
                                                    {$siteName}
                                                </div>
                                                <div style='font-size:12px;color:#93c5fd;margin-top:4px;'>
                                                    {$typeName}
                                                </div>
                                            </div>
                                            <span style='background:{$statusColor};color:#fff;font-size:11px;font-weight:700;
                                                padding:4px 14px;border-radius:999px;letter-spacing:.04em;white-space:nowrap;'>
                                                {$statusLabel}
                                            </span>
                                        </div>
                                        <div style='display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;'>
                                            <div style='background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;'>
                                                <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>Status Date</div>
                                                <div style='font-size:13px;font-weight:600;color:#fff;'>{$statusDateFormatted}</div>
                                            </div>
                                            <div style='background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;'>
                                                <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>Issuance Type</div>
                                                <div style='font-size:13px;font-weight:600;color:#fff;'>{$typeName}</div>
                                            </div>
                                            <div style='background:rgba(255,255,255,.08);border-radius:8px;padding:10px 14px;'>
                                                <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>Total Recipients</div>
                                                <div style='font-size:13px;font-weight:600;color:#fff;'>" . $record->uniformIssuanceRecipient->count() . " employee(s)</div>
                                            </div>
                                            <div style='background:rgba(255,255,255,.08);border-radius:8px;padding:10px 14px;'>
                                                <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>For Transmit</div>
                                                <div style='font-size:13px;font-weight:600;color:#fff;'>" . ($record->is_for_transmit ? 'Yes' : 'No') . "</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ── Grand total bar ── -->
                                    {$grandTotalBar}

                                    <!-- ── Recipient blocks ── -->
                                    <div style='border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:4px;'>
                                        <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                            Recipients & Items
                                        </div>
                                        <div style='padding:12px;'>
                                            {$recipientBlocks}
                                        </div>
                                    </div>

                                    <!-- ── Linked transmittals ── -->
                                    {$linkedTransmittalsHtml}

                                </div>
                            ");
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    // ─── ISSUED: pending or partial ────────────────────────────
                    Action::make('issued')
                        ->color('success')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->modalWidth('2xl')
                        ->visible(fn ($record) => in_array($record->uniform_issuance_status, ['pending', 'partial']))
                    
                        ->form(function ($record) {
                            $record->load(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                            );
                    
                            $fields = [];
                    
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                $items = [];
                    
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $remaining = (int) $item->remaining_quantity;
                                    if ($remaining <= 0) continue;
                    
                                    $itemName = $item->uniformItem?->uniform_item_name        ?? '—';
                                    $size     = $item->uniformItemVariant?->uniform_item_size  ?? '—';
                                    $typeName = $item->uniformIssuanceType?->uniform_issuance_type_name ?? '—';
                    
                                    // Include the issuance type in the label so the user can
                                    // distinguish two rows that share the same item + size but
                                    // have different types (e.g. "New Hire" vs "Salary Deduct").
                                    $label = "{$itemName} — {$size} [{$typeName}] (remaining: {$remaining})";
                    
                                    $items[] = \Filament\Forms\Components\TextInput::make("item_{$item->id}_released")
                                        ->label($label)
                                        ->numeric()
                                        ->default($remaining)
                                        ->minValue(0)
                                        ->maxValue($remaining)
                                        ->required();
                                }
                    
                                if (! empty($items)) {
                                    $fields[] = \Filament\Forms\Components\Placeholder::make("recipient_header_{$recipient->id}")
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString(
                                            "<strong style='font-size:1rem;'>{$recipient->employee_name}</strong>"
                                        ))
                                        ->columnSpanFull();
                    
                                    foreach ($items as $field) {
                                        $fields[] = $field;
                                    }
                                }
                            }
                    
                            return $fields;
                        })
                    
                        ->action(function ($record, array $data, \Filament\Actions\Action $action) {
                            $record->load(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                            );
                    
                            // ── PASS 1: compute planned deductions per variant ───────────────────────
                            // Track how much we plan to deduct per variant so we can detect
                            // over-deduction when multiple rows share the same variant.
                            $plannedDeductions = []; // [variantId => totalPlannedQty]
                    
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $newlyReleased = (int) ($data["item_{$item->id}_released"] ?? 0);
                                    if ($newlyReleased <= 0) continue;
                    
                                    $variantId = $item->uniform_item_variant_id;
                                    $plannedDeductions[$variantId] = ($plannedDeductions[$variantId] ?? 0) + $newlyReleased;
                                }
                            }
                    
                            // ── PASS 2: validate each variant's total planned deduction vs stock ─────
                            foreach ($plannedDeductions as $variantId => $totalPlanned) {
                                $variant = \App\Models\UniformItemVariants::find($variantId);
                    
                                if (! $variant) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Variant Not Found')
                                        ->danger()
                                        ->send();
                                    $action->halt();
                                    return;
                                }
                    
                                $currentStock = (int) $variant->uniform_item_quantity;
                    
                                if ($totalPlanned > $currentStock) {
                                    // Find item names for a helpful error message
                                    $itemNames = [];
                                    foreach ($record->uniformIssuanceRecipient as $recipient) {
                                        foreach ($recipient->uniformIssuanceItem as $item) {
                                            if (
                                                $item->uniform_item_variant_id == $variantId
                                                && (int) ($data["item_{$item->id}_released"] ?? 0) > 0
                                            ) {
                                                $itemNames[] = ($item->uniformItem?->uniform_item_name ?? '—')
                                                    . ' (' . ($item->uniformItemVariant?->uniform_item_size ?? '—') . ')'
                                                    . ' [' . ($item->uniformIssuanceType?->uniform_issuance_type_name ?? '—') . ']';
                                            }
                                        }
                                    }
                    
                                    \Filament\Notifications\Notification::make()
                                        ->title('Insufficient Stock')
                                        ->body(
                                            "Combined release of {$totalPlanned} for:\n"
                                            . implode("\n", $itemNames)
                                            . "\nexceeds available stock of {$currentStock}."
                                        )
                                        ->danger()
                                        ->send();
                                    $action->halt();
                                    return;
                                }
                            }
                    
                            // ── PASS 3: capture stock_before per variant BEFORE any decrement ────────
                            $stockBeforeMap = [];
                            foreach ($plannedDeductions as $variantId => $_) {
                                $variant = \App\Models\UniformItemVariants::find($variantId);
                                $stockBeforeMap[$variantId] = $variant ? (int) $variant->uniform_item_quantity : null;
                            }
                    
                            // ── PASS 4: apply deductions — one DB decrement per variant ──────────────
                            // We decrement the full planned amount for each variant in one shot
                            // so we never read stale stock mid-loop.
                            foreach ($plannedDeductions as $variantId => $totalPlanned) {
                                \App\Models\UniformItemVariants::where('id', $variantId)
                                    ->decrement('uniform_item_quantity', $totalPlanned);
                            }
                    
                            // ── PASS 5: update released/remaining per item row and build note ────────
                            $totalReleased  = 0;
                            $totalRemaining = 0;
                            $note           = [];

                            // Cache fresh variants after bulk decrement (one DB read per variant)
                            $variantCache   = [];
                            $variantRunning = []; // tracks cumulative qty consumed per variant for per-row stock calc

                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $newlyReleased = (int) ($data["item_{$item->id}_released"] ?? 0);

                                    if ($newlyReleased > 0) {
                                        $item->update([
                                            'released_quantity'  => (int) $item->released_quantity + $newlyReleased,
                                            'remaining_quantity' => (int) $item->remaining_quantity - $newlyReleased,
                                        ]);

                                        $variantId = $item->uniform_item_variant_id;

                                        // Load fresh variant once per variant id (already decremented above)
                                        if (! isset($variantCache[$variantId])) {
                                            $variantCache[$variantId] = \App\Models\UniformItemVariants::find($variantId);
                                        }

                                        $variant       = $variantCache[$variantId];
                                        $stockFinal    = $variant ? (int) $variant->uniform_item_quantity : 0;
                                        $totalDeducted = $plannedDeductions[$variantId] ?? 0;
                                        $stockOriginal = $stockFinal + $totalDeducted; // before any deductions this session

                                        // How much was already consumed by earlier rows for this variant in this loop
                                        $alreadyConsumed = $variantRunning[$variantId] ?? 0;

                                        $stockBefore = $stockOriginal - $alreadyConsumed;
                                        $stockAfter  = $stockBefore   - $newlyReleased;

                                        $variantRunning[$variantId] = $alreadyConsumed + $newlyReleased;

                                        $typeName = $item->uniformIssuanceType?->uniform_issuance_type_name ?? '—';

                                        $note[] = [
                                            'label'        => ($item->uniformItem?->uniform_item_name ?? '—')
                                                            . ' (' . ($item->uniformItemVariant?->uniform_item_size ?? '—') . ')'
                                                            . ' [' . $typeName . ']'
                                                            . ' — ' . ($recipient->employee_name ?? '—'),
                                            'released'     => $newlyReleased,
                                            'stock_before' => $stockBefore,
                                            'stock_after'  => $stockAfter,
                                        ];
                                    }

                                    $item->refresh();
                                    $totalReleased  += (int) $item->released_quantity;
                                    $totalRemaining += (int) $item->remaining_quantity;
                                }
                            }

                            // Sort: lowest stock_after first
                            usort($note, fn ($a, $b) => ($a['stock_after'] ?? 0) <=> ($b['stock_after'] ?? 0));
                    
                            // ── PASS 6: update issuance status ──────────────────────────────────────
                            if ($totalRemaining === 0) {
                                $status = 'issued';
                            } elseif ($totalReleased === 0) {
                                $status = 'pending';
                            } else {
                                $status = 'partial';
                            }
                    
                            $statusBefore = $record->uniform_issuance_status;
                    
                            $record->update([
                                'uniform_issuance_status' => $status,
                                'issued_at'               => $status === 'issued'  ? now()->toDateString() : null,
                                'partial_at'              => $status === 'partial' ? now()->toDateString() : null,
                            ]);
                    
                            \App\Models\UniformIssuanceLog::create([
                                'uniform_issuance_id' => $record->id,
                                'user_id'             => \Illuminate\Support\Facades\Auth::id(),
                                'action'              => $status,
                                'status_from'         => $statusBefore,
                                'status_to'           => $status,
                                'note'                => json_encode($note),
                            ]);
                    
                            if ($status === 'issued') {
                                \Filament\Notifications\Notification::make()
                                    ->title('Issued')
                                    ->body('All items have been fully issued.')
                                    ->success()
                                    ->send();
                            } elseif ($status === 'partial') {
                                \Filament\Notifications\Notification::make()
                                    ->title('Partial Issued')
                                    ->body('Some items have been issued. Remaining items are still pending.')
                                    ->warning()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('No Items Issued')
                                    ->body('No quantities were entered. Nothing was updated.')
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // ─── CANCEL: only when pending ─────────────────────────────
                    Action::make('cancel')
                        ->color('danger')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->uniform_issuance_status === 'pending')
                        ->action(function ($record) {
                            $record->update([
                                'uniform_issuance_status' => 'cancelled',
                                'cancelled_at'            => now()->toDateString(),
                            ]);

                            UniformIssuanceLog::create([
                                'uniform_issuance_id' => $record->id,
                                'user_id'             => Auth::id(),
                                'action'              => 'cancelled',
                                'status_from'         => $record->uniform_issuance_status,
                                'status_to'           => 'cancelled',
                                'note'                => 'Issuance was cancelled.',
                            ]);

                            Notification::make()->title('Cancelled')->body('Issuance has been cancelled.')->danger()->send();
                        }),

                    // ─── CHANGE ITEM: issued or partial ───────────────────────
                    // ─── CHANGE ITEM: issued or partial ───────────────────────
                    // Action::make('change_item')
                    //     ->color('info')
                    //     ->icon('heroicon-o-arrows-right-left')
                    //     ->modalWidth('3xl')
                    //     ->visible(function ($record) {
                    //         if (!in_array($record->uniform_issuance_status, ['issued', 'partial'])) {
                    //             return false;
                    //         }
                    //         if ($record->is_for_transmit) {
                    //             return false;
                    //         }
                    //         if (\App\Models\UniformIssuanceBilling::where('uniform_issuance_id', $record->id)->exists()) {
                    //             return false;
                    //         }
                    //         return true;
                    //     })
                    //     ->form(function ($record) {
                    //         $isPartial       = $record->uniform_issuance_status === 'partial';
                    //         $employeeOptions = [];
                    //         $itemsByEmployee = [];

                    //         foreach ($record->uniformIssuanceRecipient as $recipient) {
                    //             $name  = $recipient->employee_name;
                    //             $items = [];

                    //             foreach ($recipient->uniformIssuanceItem as $item) {
                    //                 $released = (int) $item->released_quantity;
                    //                 if ($isPartial && $released <= 0) continue;

                    //                 $qty              = $isPartial ? $released : (int) $item->quantity;
                    //                 $items[$item->id] = "{$item->uniformItem->uniform_item_name} ({$item->uniformItemVariant->uniform_item_size}) × {$qty}";
                    //             }

                    //             if (!empty($items)) {
                    //                 $employeeOptions[$name] = $name;
                    //                 $itemsByEmployee[$name] = $items;
                    //             }
                    //         }

                    //         $itemsByEmployeeJson = htmlspecialchars(json_encode($itemsByEmployee), ENT_QUOTES);

                    //         return [
                    //             Placeholder::make('_map')
                    //                 ->label('')
                    //                 ->content(new HtmlString(
                    //                     "<script>window._changeItemMap = {$itemsByEmployeeJson};</script>"
                    //                 ))
                    //                 ->columnSpanFull(),

                    //             \Filament\Forms\Components\Repeater::make('changes')
                    //                 ->label('Items to Change')
                    //                 ->addActionLabel('+ Add Another Change')
                    //                 ->minItems(1)
                    //                 ->defaultItems(1)
                    //                 ->columnSpanFull()
                    //                 ->schema([
                    //                     Select::make('employee')
                    //                         ->label('Employee')
                    //                         ->options($employeeOptions)
                    //                         ->required()
                    //                         ->live()
                    //                         ->afterStateUpdated(function (callable $set) {
                    //                             $set('from_item_id', null);
                    //                         }),

                    //                     Select::make('from_item_id')
                    //                         ->label('Item to Change')
                    //                         ->options(function (callable $get) use ($itemsByEmployee) {
                    //                             $emp = $get('employee');
                    //                             return $emp ? ($itemsByEmployee[$emp] ?? []) : [];
                    //                         })
                    //                         ->required()
                    //                         ->live()
                    //                         ->searchable(),

                    //                     TextInput::make('change_qty')
                    //                         ->label('Quantity to Change')
                    //                         ->helperText('How many of this item are being swapped out')
                    //                         ->numeric()
                    //                         ->minValue(1)
                    //                         ->required(),

                    //                     Select::make('to_item_id')
                    //                         ->label('Replacement Item')
                    //                         ->options(UniformItems::pluck('uniform_item_name', 'id'))
                    //                         ->required()
                    //                         ->live()
                    //                         ->searchable()
                    //                         ->afterStateUpdated(function (callable $set) {
                    //                             $set('to_variant_id', null);
                    //                         }),

                    //                     Select::make('to_variant_id')
                    //                         ->label('Replacement Size / Variant')
                    //                         ->options(function (callable $get) {
                    //                             $itemId = $get('to_item_id');
                    //                             if (!$itemId) return [];
                    //                             return UniformItemVariants::where('uniform_item_id', $itemId)
                    //                                 ->pluck('uniform_item_size', 'id');
                    //                         })
                    //                         ->required()
                    //                         ->live()
                    //                         ->searchable(),

                    //                     TextInput::make('replacement_qty')
                    //                         ->label('Replacement Quantity')
                    //                         ->helperText('How many replacement items to issue')
                    //                         ->numeric()
                    //                         ->minValue(1)
                    //                         ->required(),
                    //                 ]),
                    //         ];
                    //     })
                    //     ->action(function ($record, array $data, Action $action) {
                    //         $isPartial = $record->uniform_issuance_status === 'partial';
                    //         $changes   = $data['changes'] ?? [];

                    //         if (empty($changes)) {
                    //             Notification::make()->title('No Changes')->body('Add at least one change.')->warning()->send();
                    //             $action->halt();
                    //             return;
                    //         }

                    //         // ── Validate all rows first before making any changes ──
                    //         $resolved = [];
                    //         foreach ($changes as $idx => $row) {
                    //             $fromItemId     = (int) ($row['from_item_id'] ?? 0);
                    //             $changeQty      = (int) ($row['change_qty'] ?? 0);
                    //             $toItemId       = (int) ($row['to_item_id'] ?? 0);
                    //             $toVariantId    = (int) ($row['to_variant_id'] ?? 0);
                    //             $replacementQty = (int) ($row['replacement_qty'] ?? 0);

                    //             if (!$fromItemId || !$toVariantId || $changeQty <= 0 || $replacementQty <= 0) {
                    //                 Notification::make()
                    //                     ->title('Row ' . ($idx + 1) . ' is incomplete')
                    //                     ->warning()
                    //                     ->send();
                    //                 $action->halt();
                    //                 return;
                    //             }

                    //             $issuanceItem    = null;
                    //             $recipientRecord = null;

                    //             foreach ($record->uniformIssuanceRecipient as $recipient) {
                    //                 $found = $recipient->uniformIssuanceItem->firstWhere('id', $fromItemId);
                    //                 if ($found) {
                    //                     $issuanceItem    = $found;
                    //                     $recipientRecord = $recipient;
                    //                     break;
                    //                 }
                    //             }

                    //             if (!$issuanceItem) {
                    //                 Notification::make()->title('Item not found')->danger()->send();
                    //                 $action->halt();
                    //                 return;
                    //             }

                    //             $currentReleased = (int) $issuanceItem->released_quantity;
                    //             if ($changeQty > $currentReleased) {
                    //                 Notification::make()
                    //                     ->title('Change Qty Exceeds Released')
                    //                     ->body("You can only change up to {$currentReleased} of that item.")
                    //                     ->danger()
                    //                     ->send();
                    //                 $action->halt();
                    //                 return;
                    //             }

                    //             $toVariant = UniformItemVariants::find($toVariantId);
                    //             if (!$toVariant) {
                    //                 Notification::make()->title('Replacement Variant Not Found')->danger()->send();
                    //                 $action->halt();
                    //                 return;
                    //             }

                    //             if ((int) $toVariant->uniform_item_quantity < $replacementQty) {
                    //                 Notification::make()
                    //                     ->title('Insufficient Stock')
                    //                     ->body("'{$toVariant->uniformItem?->uniform_item_name} - {$toVariant->uniform_item_size}' only has {$toVariant->uniform_item_quantity} in stock but you need {$replacementQty}.")
                    //                     ->danger()
                    //                     ->send();
                    //                 $action->halt();
                    //                 return;
                    //             }

                    //             $resolved[] = [
                    //                 'item'            => $issuanceItem,
                    //                 'recipient'       => $recipientRecord,
                    //                 'change_qty'      => $changeQty,
                    //                 'to_item_id'      => $toItemId,
                    //                 'to_variant_id'   => $toVariantId,
                    //                 'to_variant'      => $toVariant,
                    //                 'replacement_qty' => $replacementQty,
                    //             ];
                    //         }

                    //         // ── Apply all changes ──
                    //         $changeNote = [];

                    //         foreach ($resolved as $r) {
                    //             $item             = $r['item'];
                    //             $recipient        = $r['recipient'];
                    //             $changeQty        = $r['change_qty'];
                    //             $toVariantId      = $r['to_variant_id'];
                    //             $toItemId         = $r['to_item_id'];
                    //             $replacementQty   = $r['replacement_qty'];
                    //             $currentReleased  = (int) $item->released_quantity;
                    //             $currentRemaining = (int) $item->remaining_quantity;
                    //             $currentQty       = (int) $item->quantity;

                    //             $oldVariant   = UniformItemVariants::find($item->uniform_item_variant_id);
                    //             $newVariant   = UniformItemVariants::find($toVariantId);
                    //             $oldItemModel = \App\Models\UniformItems::find($item->uniform_item_id);
                    //             $newItemModel = \App\Models\UniformItems::find($toItemId);

                    //             // ── Adjust stock ──
                    //             if ($oldVariant && $changeQty > 0) {
                    //                 $oldVariant->increment('uniform_item_quantity', $changeQty);
                    //             }
                    //             if ($newVariant) {
                    //                 $newVariant->decrement('uniform_item_quantity', $replacementQty);
                    //             }

                    //             // ── Update or delete the original issuance item ──
                    //             $reducedReleased = $currentReleased - $changeQty;
                    //             $reducedQty      = $currentQty - $changeQty;

                    //             if ($reducedReleased <= 0 && $currentRemaining <= 0) {
                    //                 $item->delete();
                    //             } else {
                    //                 $item->update([
                    //                     'quantity'           => max(0, $reducedQty),
                    //                     'released_quantity'  => max(0, $reducedReleased),
                    //                     'remaining_quantity' => $isPartial
                    //                         ? $currentRemaining
                    //                         : max(0, $reducedQty - max(0, $reducedReleased)),
                    //                 ]);
                    //             }

                    //             // ── Upsert replacement item ──
                    //             // If same item+variant already exists for this recipient, increment quantities
                    //             $existingIssuanceItem = \App\Models\UniformIssuanceItems::where([
                    //                 'uniform_issuance_recipient_id' => $recipient->id,
                    //                 'uniform_item_id'               => $toItemId,
                    //                 'uniform_item_variant_id'       => $toVariantId,
                    //             ])->first();

                    //             if ($existingIssuanceItem) {
                    //                 $existingIssuanceItem->increment('quantity',          $replacementQty);
                    //                 $existingIssuanceItem->increment('released_quantity', $replacementQty);
                    //                 // remaining_quantity stays as-is (already fully issued)
                    //             } else {
                    //                 $newIssuanceItem = $item->replicate(['id', 'created_at', 'updated_at']);
                    //                 $newIssuanceItem->uniform_item_id         = $toItemId;
                    //                 $newIssuanceItem->uniform_item_variant_id = $toVariantId;
                    //                 $newIssuanceItem->quantity                = $replacementQty;
                    //                 $newIssuanceItem->released_quantity       = $replacementQty;
                    //                 $newIssuanceItem->remaining_quantity      = 0;
                    //                 $newIssuanceItem->save();
                    //             }

                    //             $oldStockAfter  = (int) $oldVariant->fresh()->uniform_item_quantity;
                    //             $oldStockBefore = $oldStockAfter - $changeQty; 

                    //             $newStockAfter  = (int) $newVariant->fresh()->uniform_item_quantity;
                    //             $newStockBefore = $newStockAfter + $replacementQty; 

                    //             $changeNote[] = [
                    //                 'label'           => $recipient->employee_name,
                    //                 'released'        => $replacementQty,
                    //                 '_from'           => "{$oldItemModel?->uniform_item_name} ({$oldVariant?->uniform_item_size}) × {$changeQty}",
                    //                 '_to'             => "{$newItemModel?->uniform_item_name} ({$newVariant?->uniform_item_size}) × {$replacementQty}",
                    //                 '_new_item_name'  => $newItemModel?->uniform_item_name ?? '—',
                    //                 '_new_item_size'  => $newVariant?->uniform_item_size ?? '—',
                    //                 '_employee'       => $recipient->employee_name,
                    //                 '_old_item_id'    => $item->uniform_item_id,
                    //                 '_old_variant_id' => $item->uniform_item_variant_id,
                    //                 '_change_qty'     => $changeQty,
                    //                 '_release_label'  => "{$newItemModel?->uniform_item_name} ({$newVariant?->uniform_item_size}) — {$recipient->employee_name}",
                    //                 'old_stock_before' => $oldStockBefore,
                    //                 'old_stock_after'  => $oldStockAfter,
                    //                 'new_stock_before' => $newStockBefore,
                    //                 'new_stock_after'  => $newStockAfter,
                    //             ];
                    //         }

                    //         // ── Log: item_changed ──
                    //         UniformIssuanceLog::create([
                    //             'uniform_issuance_id' => $record->id,
                    //             'user_id'             => Auth::id(),
                    //             'action'              => 'item_changed',
                    //             'status_from'         => $record->uniform_issuance_status,
                    //             'status_to'           => $record->uniform_issuance_status,
                    //             'note'                => json_encode($changeNote),
                    //         ]);

                    //         // ── Log: item_released ──
                    //         $releaseNote = array_map(fn ($c) => [
                    //             'label'    => $c['_release_label'],
                    //             'released' => $c['released'],
                    //         ], $changeNote);

                    //         UniformIssuanceLog::create([
                    //             'uniform_issuance_id' => $record->id,
                    //             'user_id'             => Auth::id(),
                    //             'action'              => 'item_released',
                    //             'status_from'         => $record->uniform_issuance_status,
                    //             'status_to'           => $record->uniform_issuance_status,
                    //             'note'                => json_encode($releaseNote),
                    //         ]);

                    //         // ── Sync transmittal items_summary if any exist ──
                    //         $existingTransmittals = \App\Models\Transmittals::where('uniform_issuance_id', $record->id)->get();

                    //         if ($existingTransmittals->count() > 0) {
                    //             $freshRecord = \App\Models\UniformIssuances::with([
                    //                 'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                    //                 'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                    //             ])->find($record->id);

                    //             $summaryMap = [];
                    //             foreach ($freshRecord->uniformIssuanceRecipient as $recipient) {
                    //                 foreach ($recipient->uniformIssuanceItem as $item) {
                    //                     $qty = (int) ($item->released_quantity ?: $item->quantity);
                    //                     if ($qty <= 0) continue;
                    //                     $itemName = $item->uniformItem?->uniform_item_name ?? '—';
                    //                     $size     = $item->uniformItemVariant?->uniform_item_size ?? '—';
                    //                     $key      = $itemName . '||' . $size;
                    //                     if (!isset($summaryMap[$key])) {
                    //                         $summaryMap[$key] = ['item_name' => $itemName, 'size' => $size, 'qty' => 0];
                    //                     }
                    //                     $summaryMap[$key]['qty'] += $qty;
                    //                 }
                    //             }
                    //             $newSummary = array_values($summaryMap);

                    //             foreach ($existingTransmittals as $txn) {
                    //                 $txn->update(['items_summary' => $newSummary]);
                    //             }
                    //         }

                    //         Notification::make()
                    //             ->title('Items Changed')
                    //             ->body(count($changeNote) . ' change(s) applied successfully.')
                    //             ->success()
                    //             ->send();
                    //     }),
                

                    // ─── LOGS ──────────────────────────────────────────────────
                    Action::make('view_logs')
                        ->label('Logs')
                        ->color('gray')
                        ->icon('heroicon-o-clock')
                        ->modalContent(function ($record) {
                            $logs = \App\Models\UniformIssuanceLog::where('uniform_issuance_id', $record->id)
                                ->with('user')
                                ->latest()
                                ->get();

                            $rows = $logs->map(function ($log) {
                                $user   = $log->user?->name ?? 'System';
                                $date   = \Carbon\Carbon::parse($log->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A');
                                $from   = $log->status_from ?? '—';
                                $to     = $log->status_to ?? '—';
                                $action = strtoupper($log->action);

                                $badgeColor = match($log->action) {
                                    'issued'        => '#16a34a',
                                    'partial'       => '#d97706',
                                    'cancelled'     => '#dc2626',
                                    'item_changed'  => '#2563eb',
                                    'item_released' => '#0d9488',
                                    'created'       => '#7c3aed',
                                    'edited'        => '#0891b2',
                                    default         => '#6b7280',
                                };

                                $itemsHtml = '';
                                if (!empty($log->note)) {
                                    $noteData = json_decode($log->note, true);
                                    if (is_array($noteData)) {
                                        if ($log->action === 'item_changed') {
                                            $itemsHtml = "<div style='margin-top:6px;padding:6px 8px;background:#f8fafc;border-radius:6px;border:1px solid #e2e8f0;'>";
                                            $itemsHtml .= "<div style='font-size:10px;font-weight:700;color:#475569;margin-bottom:4px;'>ITEM CHANGES:</div>";
                                            foreach ($noteData as $row) {
                                                $employee = e($row['label'] ?? '—');
                                                $fromStr  = e($row['_from'] ?? '—');
                                                $toStr    = e($row['_to'] ?? '—');

                                                $oldStockHtml = '';
                                                if (isset($row['old_stock_before'], $row['old_stock_after'])) {
                                                    $oldStockHtml = "<br><span style='font-size:10px;color:#9ca3af;'>
                                                        returned stock: {$row['old_stock_before']} → <strong style='color:#16a34a;'>{$row['old_stock_after']}</strong>
                                                    </span>";
                                                }

                                                $newStockHtml = '';
                                                if (isset($row['new_stock_before'], $row['new_stock_after'])) {
                                                    $newStockHtml = "<br><span style='font-size:10px;color:#9ca3af;'>
                                                        deducted stock: {$row['new_stock_before']} → <strong style='color:#dc2626;'>{$row['new_stock_after']}</strong>
                                                    </span>";
                                                }

                                                $itemsHtml .= "
                                                    <div style='font-size:11px;color:#374151;padding:3px 0;border-bottom:1px dashed #e5e7eb;'>
                                                        <span style='font-weight:600;color:#1e3a5f;'>{$employee}</span><br>
                                                        <span style='color:#dc2626;'>FROM: {$fromStr}</span>{$oldStockHtml}<br>
                                                        <span style='color:#16a34a;'>TO: &nbsp;&nbsp; {$toStr}</span>{$newStockHtml}
                                                    </div>";
                                            }
                                            $itemsHtml .= "</div>";
                                        } elseif (in_array($log->action, ['issued', 'partial', 'item_released'])) {
                                            $itemsHtml = "<div style='margin-top:6px;padding:6px 8px;background:#f0fdf4;border-radius:6px;border:1px solid #bbf7d0;'>";
                                            $itemsHtml .= "<div style='font-size:10px;font-weight:700;color:#166534;margin-bottom:4px;'>ITEMS RELEASED:</div>";
                                            foreach ($noteData as $row) {
                                                    $label       = e($row['label'] ?? '—');
                                                    $released    = (int) ($row['released'] ?? 0);
                                                    $stockBefore = isset($row['stock_before']) ? (int) $row['stock_before'] : null;
                                                    $stockAfter  = isset($row['stock_after'])  ? (int) $row['stock_after']  : null;

                                                    $stockHtml = '';
                                                    if ($stockBefore !== null && $stockAfter !== null) {
                                                        $stockHtml = "<span style='font-size:10px;color:#9ca3af;margin-left:6px;'>
                                                            stock: <span style='color:#374151;font-weight:600;'>{$stockBefore}</span>
                                                            → <span style='color:" . ($stockAfter >= 0 ? '#16a34a' : '#dc2626') . ";font-weight:700;'>{$stockAfter}</span>
                                                        </span>";
                                                    }

                                                    $itemsHtml .= "
                                                        <div style='font-size:11px;color:#374151;padding:3px 0;border-bottom:1px dashed #d1fae5;display:flex;justify-content:space-between;align-items:center;'>
                                                            <span>{$label}{$stockHtml}</span>
                                                            <span style='font-weight:700;color:#16a34a;'>×{$released}</span>
                                                        </div>";
                                                }
                                            $itemsHtml .= "</div>";
                                        } else {
                                            $itemsHtml = "<div style='margin-top:4px;font-size:11px;color:#6b7280;font-style:italic;'>" . e($log->note) . "</div>";
                                        }
                                    } else {
                                        $itemsHtml = "<div style='margin-top:4px;font-size:11px;color:#6b7280;font-style:italic;'>" . e($log->note) . "</div>";
                                    }
                                }

                                return "
                                    <tr>
                                        <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                            <div style='font-size:11px;color:#374151;white-space:nowrap;'>{$date}</div>
                                            <div style='font-size:10px;color:#9ca3af;margin-top:2px;'>{$user}</div>
                                        </td>
                                        <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                            <span style='background:{$badgeColor};color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;'>{$action}</span>
                                        </td>
                                        <td style='padding:10px;border-bottom:1px solid #e5e7eb;vertical-align:top;'>
                                            <div style='font-size:11px;color:#374151;'>{$from} → {$to}</div>
                                            {$itemsHtml}
                                        </td>
                                    </tr>";
                            })->implode('');

                            return new \Illuminate\Support\HtmlString("
                                <div style='max-height:500px;overflow-y:auto;'>
                                    <table style='width:100%;border-collapse:collapse;'>
                                        <thead style='position:sticky;top:0;z-index:1;'>
                                            <tr style='background:#1e3a5f;'>
                                                <th style='padding:8px 10px;text-align:left;font-size:11px;color:#fff;white-space:nowrap;'>Date / By</th>
                                                <th style='padding:8px 10px;text-align:left;font-size:11px;color:#fff;'>Action</th>
                                                <th style='padding:8px 10px;text-align:left;font-size:11px;color:#fff;'>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                </div>
                            ");
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                    // ─── RECEIVING COPY ────────────────────────────────────────
                    Action::make('receiving_copy')
                        ->label('Receiving Copy')
                        ->color('gray')
                        ->icon('heroicon-o-document-text')
                        ->visible(fn ($record) => in_array($record->uniform_issuance_status, ['partial', 'issued']))
                        ->modalContent(function ($record) {
                            $siteName = e($record->site->site_name ?? '—');
                            $typeName = e($record->uniformIssuanceType->uniform_issuance_type_name ?? '—');
                            $printUrl = route('uniform-issuances.receiving-copy', $record->id);

                            $allLogs = \App\Models\UniformIssuanceLog::where('uniform_issuance_id', $record->id)
                                ->whereIn('action', ['issued', 'partial', 'item_changed', 'item_released'])
                                ->with('user')
                                ->latest()
                                ->get();

                            $buildCompleteCard = function () use ($record, $siteName, $typeName, $printUrl, $allLogs): string {
                                $rows = '';
                                foreach ($record->uniformIssuanceRecipient as $recipient) {
                                    foreach ($recipient->uniformIssuanceItem as $item) {
                                        $released = (int) $item->released_quantity;
                                        if ($released <= 0) continue;
                                        $label = e("{$item->uniformItem->uniform_item_name} ({$item->uniformItemVariant->uniform_item_size}) — {$recipient->employee_name}");
                                        $rows .= "
                                            <tr>
                                                <td style='padding:6px 8px;border:1px solid #d1d5db;font-size:12px;'>{$label}</td>
                                                <td style='padding:6px 8px;border:1px solid #d1d5db;font-size:12px;text-align:center;font-weight:700;'>{$released}</td>
                                            </tr>";
                                    }
                                }

                                $finalStatus     = $record->uniform_issuance_status === 'issued' ? 'FULLY ISSUED' : 'PARTIAL';
                                $finalBadgeColor = $record->uniform_issuance_status === 'issued' ? '#16a34a' : '#d97706';
                                $lastLog         = $allLogs->last();
                                $lastDate        = $lastLog
                                    ? \Carbon\Carbon::parse($lastLog->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A')
                                    : now()->timezone('Asia/Manila')->format('M d, Y h:i A');
                                $lastUser = $lastLog?->user?->name ?? 'System';

                                return "
                                    <div style='border:2px solid #1e3a5f;border-radius:8px;padding:16px;margin-bottom:16px;background:#f0f4ff;box-shadow:0 2px 6px rgba(0,0,0,.08);'>
                                        <div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;'>
                                            <div>
                                                <div style='font-size:14px;font-weight:800;color:#1e3a5f;'>Complete Receiving Copy</div>
                                                <div style='font-size:11px;color:#6b7280;margin-top:2px;'>{$siteName} &bull; {$typeName}</div>
                                                <div style='font-size:10px;color:#9ca3af;margin-top:1px;'>{$lastDate} &bull; by {$lastUser}</div>
                                            </div>
                                            <span style='background:{$finalBadgeColor};color:#fff;font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap;'>{$finalStatus}</span>
                                        </div>
                                        <table style='width:100%;border-collapse:collapse;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:6px 8px;text-align:left;font-size:11px;color:#fff;border:1px solid #1e3a5f;'>Item / Recipient</th>
                                                    <th style='padding:6px 8px;text-align:center;font-size:11px;color:#fff;border:1px solid #1e3a5f;width:80px;'>Total Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$rows}</tbody>
                                        </table>
                                        <div style='margin-top:10px;text-align:right;'>
                                            <a href='{$printUrl}' target='_blank' style='font-size:11px;color:#2563eb;text-decoration:underline;'>
                                                Open Printable Copy ↗
                                            </a>
                                        </div>
                                    </div>";
                            };

                            $buildIssuanceCard = function (
                                string $title,
                                string $badgeColor,
                                string $badgeLabel,
                                string $date,
                                string $byUser,
                                array  $noteData,
                                string $cardPrintUrl
                            ) use ($siteName, $typeName): string {
                                $rows = '';
                                foreach ($noteData as $row) {
                                    $label    = e($row['label'] ?? '—');
                                    $released = (int) ($row['released'] ?? 0);
                                    $rows .= "
                                        <tr>
                                            <td style='padding:6px 8px;border:1px solid #d1d5db;font-size:12px;'>{$label}</td>
                                            <td style='padding:6px 8px;border:1px solid #d1d5db;font-size:12px;text-align:center;font-weight:700;'>{$released}</td>
                                        </tr>";
                                }

                                return "
                                    <div style='border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:16px;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06);'>
                                        <div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;'>
                                            <div>
                                                <div style='font-size:13px;font-weight:700;color:#1e3a5f;'>{$title}</div>
                                                <div style='font-size:11px;color:#6b7280;margin-top:2px;'>{$siteName} &bull; {$typeName}</div>
                                                <div style='font-size:10px;color:#9ca3af;margin-top:1px;'>{$date} &bull; by {$byUser}</div>
                                            </div>
                                            <span style='background:{$badgeColor};color:#fff;font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap;'>{$badgeLabel}</span>
                                        </div>
                                        <table style='width:100%;border-collapse:collapse;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:6px 8px;text-align:left;font-size:11px;color:#fff;border:1px solid #1e3a5f;'>Item / Recipient</th>
                                                    <th style='padding:6px 8px;text-align:center;font-size:11px;color:#fff;border:1px solid #1e3a5f;width:80px;'>Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$rows}</tbody>
                                        </table>
                                        <div style='margin-top:10px;text-align:right;'>
                                            <a href='{$cardPrintUrl}' target='_blank' style='font-size:11px;color:#2563eb;text-decoration:underline;'>
                                                Open Printable Copy ↗
                                            </a>
                                        </div>
                                    </div>";
                            };

                            $buildChangedCard = function (
                                string $title,
                                string $date,
                                string $byUser,
                                array  $noteData,
                                string $completePrintUrl
                            ) use ($siteName, $typeName, $record): string {

                                $completeRows = '';
                                foreach ($record->uniformIssuanceRecipient as $recipient) {
                                    foreach ($recipient->uniformIssuanceItem as $item) {
                                        $released = (int) $item->released_quantity;
                                        if ($released <= 0) continue;
                                        $label = e("{$item->uniformItem->uniform_item_name} ({$item->uniformItemVariant->uniform_item_size}) — {$recipient->employee_name}");
                                        $completeRows .= "
                                            <tr>
                                                <td style='padding:5px 8px;border:1px solid #d1d5db;font-size:11px;'>{$label}</td>
                                                <td style='padding:5px 8px;border:1px solid #d1d5db;font-size:11px;text-align:center;font-weight:700;'>{$released}</td>
                                            </tr>";
                                    }
                                }

                                $changeRows = '';
                                foreach ($noteData as $row) {
                                    $employee = e($row['label'] ?? '—');
                                    $from     = e($row['_from'] ?? '—');
                                    $to       = e($row['_to'] ?? '—');
                                    $changeRows .= "
                                        <tr>
                                            <td style='padding:6px 8px;border:1px solid #bfdbfe;font-size:12px;font-weight:600;color:#1e3a5f;'>{$employee}</td>
                                            <td style='padding:6px 8px;border:1px solid #bfdbfe;font-size:12px;'>
                                                <span style='color:#dc2626;'>FROM: {$from}</span><br>
                                                <span style='color:#16a34a;'>TO: &nbsp;&nbsp;{$to}</span>
                                            </td>
                                        </tr>";
                                }

                                return "
                                    <div style='border:1px solid #bfdbfe;border-radius:8px;padding:16px;margin-bottom:16px;background:#eff6ff;box-shadow:0 1px 3px rgba(0,0,0,.06);'>
                                        <div style='display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;'>
                                            <div>
                                                <div style='font-size:13px;font-weight:700;color:#1e3a5f;'>{$title}</div>
                                                <div style='font-size:11px;color:#6b7280;margin-top:2px;'>{$siteName} &bull; {$typeName}</div>
                                                <div style='font-size:10px;color:#9ca3af;margin-top:1px;'>{$date} &bull; by {$byUser}</div>
                                            </div>
                                            <span style='background:#2563eb;color:#fff;font-size:10px;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap;'>ITEM CHANGED</span>
                                        </div>

                                        <div style='font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;'>
                                            Complete Receiving Copy (after change)
                                        </div>
                                        <table style='width:100%;border-collapse:collapse;margin-bottom:10px;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:5px 8px;text-align:left;font-size:10px;color:#fff;border:1px solid #1e3a5f;'>Item / Recipient</th>
                                                    <th style='padding:5px 8px;text-align:center;font-size:10px;color:#fff;border:1px solid #1e3a5f;width:60px;'>Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$completeRows}</tbody>
                                        </table>
                                        <div style='margin-bottom:12px;text-align:right;'>
                                            <a href='{$completePrintUrl}' target='_blank' style='font-size:11px;color:#2563eb;text-decoration:underline;'>
                                                Print Complete Copy ↗
                                            </a>
                                        </div>

                                        <div style='border-top:1px dashed #bfdbfe;padding-top:10px;'>
                                            <div style='font-size:10px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;'>
                                                What Changed
                                            </div>
                                            <table style='width:100%;border-collapse:collapse;'>
                                                <thead>
                                                    <tr style='background:#1e40af;'>
                                                        <th style='padding:6px 8px;text-align:left;font-size:11px;color:#fff;border:1px solid #1e40af;width:35%;'>Recipient</th>
                                                        <th style='padding:6px 8px;text-align:left;font-size:11px;color:#fff;border:1px solid #1e40af;'>Change Detail</th>
                                                    </tr>
                                                </thead>
                                                <tbody>{$changeRows}</tbody>
                                            </table>
                                            </div>
                                    </div>";
                            };

                            $completeCard    = $buildCompleteCard();
                            $individualCards = '';

                            $totalBatches  = $allLogs->whereIn('action', ['issued', 'partial', 'item_released'])->count();
                            $totalChanges  = $allLogs->where('action', 'item_changed')->count();
                            $batchIndex    = $totalBatches;
                            $changeIndex   = $totalChanges;

                            foreach ($allLogs as $log) {
                                $noteData = json_decode($log->note ?? '[]', true);
                                if (!is_array($noteData)) $noteData = [];

                                $date   = \Carbon\Carbon::parse($log->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A');
                                $byUser = $log->user?->name ?? 'System';

                                if ($log->action === 'item_changed') {
                                    $title            = "Change #{$changeIndex} — Item Substitution";
                                    $completePrintUrl = $printUrl;
                                    $individualCards .= $buildChangedCard($title, $date, $byUser, $noteData, $completePrintUrl);
                                    $changeIndex--;
                                } elseif ($log->action === 'item_released') {
                                    $batchPrintUrl    = route('uniform-issuances.receiving-copy', ['issuance' => $record->id, 'log' => $log->id]);
                                    $title            = "Batch #{$batchIndex} — Release (After Change)";
                                    $individualCards .= $buildIssuanceCard($title, '#0d9488', 'CHANGE RELEASE', $date, $byUser, $noteData, $batchPrintUrl);
                                    $batchIndex--;
                                } else {
                                    $completedIssuance = $log->action === 'issued';
                                    $badgeColor        = '#d97706';
                                    $badgeLabel        = 'RELEASE';
                                    $title             = "Batch #{$batchIndex} — Release"
                                                    . ($completedIssuance ? ' (Completed Issuance)' : '');
                                    $batchPrintUrl     = route('uniform-issuances.receiving-copy', ['issuance' => $record->id, 'log' => $log->id]);
                                    $individualCards  .= $buildIssuanceCard($title, $badgeColor, $badgeLabel, $date, $byUser, $noteData, $batchPrintUrl);
                                    $batchIndex--;
                                }
                            }

                            $separatorHtml = $allLogs->count() > 0
                                ? "<div style='border-top:2px dashed #e5e7eb;margin:20px 0 16px;'>
                                    <div style='font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-top:12px;margin-bottom:4px;'>
                                        Individual Transactions
                                    </div>
                                </div>"
                                : '';

                            return new \Illuminate\Support\HtmlString("
                                <div style='max-height:600px;overflow-y:auto;padding:4px;'>
                                    {$completeCard}
                                    {$separatorHtml}
                                    {$individualCards}
                                </div>
                            ");
                        })
                        ->modalHeading('Receiving Copies')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close'),

                        Action::make('transmit')
                        ->label('Transmit')
                        ->color('primary')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(fn ($record) =>
                            in_array($record->uniform_issuance_status, ['partial', 'issued'])
                            && ! $record->is_for_transmit
                        )
                        ->form([
                            \Filament\Forms\Components\TextInput::make('transmitted_to')
                                ->label('Transmitted To')
                                ->placeholder('e.g. Site Manager / Supervisor name')
                                ->required(),
                            \Filament\Forms\Components\TextInput::make('transmitted_by')
                                ->label('Transmitted By')
                                ->default(fn () => Auth::user()?->name ?? '')
                                ->required(),
                            \Filament\Forms\Components\TextInput::make('purpose')
                                ->label('Purpose')
                                ->placeholder('e.g. New hire uniform issuance'),
                            \Filament\Forms\Components\TextInput::make('instructions')
                                ->label('Instructions')
                                ->placeholder('e.g. Please sign and return copy'),
                        ])
                        ->action(function ($record, array $data, Action $action) {
                            $record->loadMissing(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant'
                            );
    
                            $summaryMap = [];
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $qty = (int) ($item->released_quantity ?: $item->quantity);
                                    if ($qty <= 0) continue;
    
                                    $itemName = $item->uniformItem?->uniform_item_name ?? '—';
                                    $size     = $item->uniformItemVariant?->uniform_item_size ?? '—';
                                    $key      = $itemName . '||' . $size;
    
                                    if (!isset($summaryMap[$key])) {
                                        $summaryMap[$key] = ['item_name' => $itemName, 'size' => $size, 'qty' => 0];
                                    }
                                    $summaryMap[$key]['qty'] += $qty;
                                }
                            }
    
                            if (empty($summaryMap)) {
                                Notification::make()
                                    ->title('Nothing to Transmit')
                                    ->body('No issued items found on this issuance.')
                                    ->warning()
                                    ->send();
                                $action->halt();
                                return;
                            }
    
                            $transmittal = \App\Models\Transmittals::create([
                                'uniform_issuance_id' => $record->id,
                                'transmittal_number'  => \App\Models\Transmittals::generateNumber(),
                                'transmitted_by'      => $data['transmitted_by'],
                                'transmitted_to'      => $data['transmitted_to'],
                                'purpose'             => $data['purpose'] ?? '',
                                'instructions'        => $data['instructions'] ?? '',
                                'items_summary'       => array_values($summaryMap),
                                'transmitted_at'      => now()->toDateString(),
                                'status'              => 'pending',
                            ]);
    
                            // Attach via pivot
                            $transmittal->issuances()->attach($record->id);
    
                            // Tag as transmitted
                            $record->update(['is_for_transmit' => true]);
    
                            $url = route('uniform-issuances.transmittal', [
                                'issuance'    => $record->id,
                                'transmittal' => $transmittal->id,
                            ]);
    
                            Notification::make()
                                ->title('Transmittal Created')
                                ->body("{$transmittal->transmittal_number} created successfully.")
                                ->success()
                                ->actions([
                                    \Filament\Actions\Action::make('open')
                                        ->label('Open Transmittal')
                                        ->url($url)
                                        ->openUrlInNewTab()
                                        ->button(),
                                ])
                                ->persistent()
                                ->send();
                        }),


                    Action::make('for_delivery_receipt')
                        ->label('For Delivery Receipt')
                        ->color('success')
                        ->icon('heroicon-o-document-check')
                        ->modalWidth('4xl')
                        ->visible(function ($record) {
                            if (!in_array($record->uniform_issuance_status, ['partial', 'issued'])) {
                                return false;
                            }
                    
                            $typeName        = strtolower($record->uniformIssuanceType?->uniform_issuance_type_name ?? '');
                            $drEligibleTypes = ['new hire', 'additional', 'annual'];
                    
                            $isDrType = false;
                            foreach ($drEligibleTypes as $t) {
                                if (str_contains($typeName, $t)) { $isDrType = true; break; }
                            }
                    
                            if (!$isDrType) return false;
                    
                            $record->loadMissing('uniformIssuanceRecipient.position');
                    
                            return $record->uniformIssuanceRecipient->contains(function ($recipient) {
                                return strtolower($recipient->position?->position_name ?? '') !== 'reliever'
                                    && strtolower($recipient->employee_status ?? '') !== 'reliever';
                            });
                        })
                        ->modalSubmitAction(function ($action, $record) {
                            if (\App\Models\ForDeliveryReceipt::where('uniform_issuance_id', $record->id)->exists()) {
                                return false;
                            }
                            return $action;
                        })
                        ->modalCancelActionLabel('Close')
                        ->modalContent(function ($record) {
                            $record->load(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.position',
                                'site',
                                'uniformIssuanceType'
                            );
                    
                            $existingDR = \App\Models\ForDeliveryReceipt::where('uniform_issuance_id', $record->id)
                                ->latest()
                                ->first();
                    
                            if ($existingDR) {
                                $statusColor = match ($existingDR->status) {
                                    'done'      => '#16a34a',
                                    'cancelled' => '#dc2626',
                                    default     => '#d97706',
                                };
                                $statusLabel = strtoupper($existingDR->status);
                                $endorseBy   = e($existingDR->endorse_by);
                                $endorseDate = $existingDR->endorse_date
                                    ? \Carbon\Carbon::parse($existingDR->endorse_date)->format('M d, Y')
                                    : '—';
                                $createdAt = \Carbon\Carbon::parse($existingDR->created_at)->timezone('Asia/Manila')->format('M d, Y h:i A');
                                $remarks   = e($existingDR->remarks ?? '—');
                    
                                $itemRows = '';
                                foreach ((array) $existingDR->item_summary as $row) {
                                    $itemName = e($row['item_name'] ?? '—');
                                    $size     = e($row['size'] ?? '—');
                                    $emp      = e($row['employee'] ?? '—');
                                    $qty      = (int) ($row['qty'] ?? 0);
                                    $itemRows .= "
                                        <tr>
                                            <td style='padding:9px 16px;font-size:12.5px;color:#111827;border-bottom:1px solid #f1f5f9;'>{$emp}</td>
                                            <td style='padding:9px 16px;font-size:12.5px;color:#374151;border-bottom:1px solid #f1f5f9;'>{$itemName}</td>
                                            <td style='padding:9px 16px;font-size:12.5px;color:#374151;text-align:center;border-bottom:1px solid #f1f5f9;'>{$size}</td>
                                            <td style='padding:9px 16px;font-size:12.5px;color:#1d4ed8;font-weight:600;text-align:center;border-bottom:1px solid #f1f5f9;'>{$qty}</td>
                                        </tr>";
                                }
                    
                                $existingHtml = "
                                    <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                    
                                        <!-- DR Record Card -->
                                        <div style='border:1.5px solid #bbf7d0;border-radius:12px;overflow:hidden;margin-bottom:12px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.05);'>
                    
                                            <!-- Card Header -->
                                            <div style='background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);padding:14px 18px;border-bottom:1px solid #bbf7d0;display:flex;justify-content:space-between;align-items:flex-start;'>
                                                <div>
                                                    <div style='font-size:13px;font-weight:700;color:#14532d;letter-spacing:-0.02em;'>
                                                        Delivery Receipt &nbsp;#DR{$existingDR->id}
                                                    </div>
                                                    <div style='font-size:11.5px;color:#4b7a5c;margin-top:4px;line-height:1.5;'>
                                                        Endorsed by <strong style='color:#14532d;'>{$endorseBy}</strong>
                                                        &nbsp;&bull;&nbsp; {$endorseDate}
                                                    </div>
                                                    <div style='font-size:11px;color:#6b7280;margin-top:3px;'>Created {$createdAt}</div>
                                                    <div style='font-size:11.5px;color:#374151;margin-top:5px;'>
                                                        <span style='color:#6b7280;'>Remarks:</span> {$remarks}
                                                    </div>
                                                </div>
                                                <span style='background:{$statusColor};color:#fff;font-size:10px;font-weight:700;
                                                    padding:4px 12px;border-radius:999px;letter-spacing:.04em;white-space:nowrap;flex-shrink:0;margin-top:2px;'>
                                                    {$statusLabel}
                                                </span>
                                            </div>
                    
                                            <!-- Items Table -->
                                            <div style='overflow-x:auto;'>
                                                <table style='width:100%;border-collapse:collapse;'>
                                                    <thead>
                                                        <tr style='background:#1e3a5f;'>
                                                            <th style='padding:9px 16px;text-align:left;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Employee</th>
                                                            <th style='padding:9px 16px;text-align:left;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Item</th>
                                                            <th style='padding:9px 16px;text-align:center;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;width:80px;'>Size</th>
                                                            <th style='padding:9px 16px;text-align:center;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;width:70px;'>Qty</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>{$itemRows}</tbody>
                                                </table>
                                            </div>
                                        </div>
                    
                                        <!-- Locked Notice -->
                                        <div style='padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;
                                            font-size:12px;color:#854d0e;text-align:center;font-weight:500;'>
                                            &#9432;&nbsp; A delivery receipt already exists for this issuance. No additional DR can be created.
                                        </div>
                    
                                    </div>";
                    
                                return new \Illuminate\Support\HtmlString($existingHtml);
                            }
                    
                            $siteName = e($record->site?->site_name ?? '—');
                            $typeName = e($record->uniformIssuanceType?->uniform_issuance_type_name ?? '—');
                            $status   = strtoupper($record->uniform_issuance_status);
                    
                            return new \Illuminate\Support\HtmlString("
                                <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                                    <div style='display:flex;align-items:center;gap:10px;padding:10px 14px;
                                        background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:12px;'>
                                        <div style='width:8px;height:8px;border-radius:50%;background:#16a34a;flex-shrink:0;'></div>
                                        <span style='font-size:13px;font-weight:600;color:#1e3a5f;'>{$siteName}</span>
                                        <span style='color:#d1d5db;'>·</span>
                                        <span style='font-size:12.5px;color:#374151;'>{$typeName}</span>
                                        <span style='margin-left:auto;font-size:11px;font-weight:700;color:#16a34a;
                                            background:#dcfce7;padding:3px 10px;border-radius:999px;letter-spacing:.03em;'>{$status}</span>
                                    </div>
                                    <div style='font-size:12px;color:#6b7280;line-height:1.6;'>
                                        The form below pre-fills item quantities based on what has been issued.
                                        Only non-reliever employees are included.
                                    </div>
                                </div>
                            ");
                        })
                        ->form(function ($record) {
                            if (\App\Models\ForDeliveryReceipt::where('uniform_issuance_id', $record->id)->exists()) {
                                return [];
                            }
                    
                            $record->load(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.position',
                            );
                    
                            $grouped = [];
                    
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                $isReliever = strtolower($recipient->position?->position_name ?? '') === 'reliever'
                                    || strtolower($recipient->employee_status ?? '') === 'reliever';
                    
                                if ($isReliever) continue;
                    
                                $employeeName  = $recipient->employee_name ?? '—';
                                $employeeItems = [];
                    
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $qty = (int) $item->released_quantity;
                                    if ($qty <= 0) continue;
                    
                                    $key = $item->uniform_item_id . '_' . $item->uniform_item_variant_id;
                                    if (!isset($employeeItems[$key])) {
                                        $employeeItems[$key] = [
                                            'employee'  => $employeeName,
                                            'item_name' => $item->uniformItem?->uniform_item_name ?? '—',
                                            'size'      => $item->uniformItemVariant?->uniform_item_size ?? '—',
                                            'qty'       => 0,
                                        ];
                                    }
                                    $employeeItems[$key]['qty'] += $qty;
                                }
                    
                                if (!empty($employeeItems)) {
                                    $grouped[$employeeName] = array_values($employeeItems);
                                }
                            }
                    
                            $itemSummaryFlat = [];
                            foreach ($grouped as $empItems) {
                                foreach ($empItems as $row) {
                                    $itemSummaryFlat[] = $row;
                                }
                            }
                    
                            $itemSummaryJson = json_encode($itemSummaryFlat, JSON_UNESCAPED_UNICODE);
                            $grandQty        = array_sum(array_column($itemSummaryFlat, 'qty'));
                            $empCount        = count($grouped);
                    
                            $fields = [];
                    
                            // ── Meta fields ──
                            $fields[] = \Filament\Forms\Components\TextInput::make('endorse_by')
                                ->label('Endorsed By')
                                ->default(fn () => \Illuminate\Support\Facades\Auth::user()?->name ?? '')
                                ->required()
                                ->columnSpanFull();
                    
                            $fields[] = \Filament\Forms\Components\DatePicker::make('endorse_date')
                                ->label('Endorse Date')
                                ->default(now()->toDateString())
                                ->required();
                    
                            $fields[] = \Filament\Forms\Components\TextInput::make('remarks')
                                ->label('Remarks')
                                ->placeholder('Optional notes or instructions');
                    
                            $fields[] = \Filament\Forms\Components\Hidden::make('item_summary')
                                ->default($itemSummaryJson);
                    
                            // ── Summary strip ──
                            $fields[] = \Filament\Forms\Components\Placeholder::make('dr_summary_header')
                                ->label('')
                                ->content(new \Illuminate\Support\HtmlString("
                                    <div style='font-family:\"DM Sans\",system-ui,sans-serif;
                                        display:flex;align-items:center;justify-content:space-between;
                                        padding:10px 0 8px;border-bottom:1.5px solid #e5e7eb;margin-top:4px;'>
                                        <span style='font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;'>
                                            {$empCount}&nbsp;employee" . ($empCount !== 1 ? 's' : '') . "
                                        </span>
                                        <span style='font-size:14px;font-weight:700;color:#16a34a;letter-spacing:-0.02em;'>
                                            {$grandQty}&nbsp;<span style='font-size:11px;font-weight:500;color:#6b7280;'>total pcs</span>
                                        </span>
                                    </div>
                                "))
                                ->columnSpanFull();
                    
                            $avatarStyles = [
                                ['bg' => '#E6F1FB', 'color' => '#0C447C'],
                                ['bg' => '#E1F5EE', 'color' => '#0F6E56'],
                                ['bg' => '#EEEDFE', 'color' => '#3C3489'],
                                ['bg' => '#FAEEDA', 'color' => '#633806'],
                                ['bg' => '#FAECE7', 'color' => '#712B13'],
                            ];
                    
                            $empIndex = 0;
                            foreach ($grouped as $employeeName => $empItems) {
                                $empIndex++;
                                $empQty   = array_sum(array_column($empItems, 'qty'));
                                $words    = explode(' ', trim($employeeName));
                                $initials = strtoupper(
                                    (isset($words[0]) ? substr($words[0], 0, 1) : '') .
                                    (isset($words[1]) ? substr($words[1], 0, 1) : '')
                                );
                                $av = $avatarStyles[($empIndex - 1) % count($avatarStyles)];
                    
                                $tableRows = '';
                                foreach ($empItems as $item) {
                                    $tableRows .= "
                                        <tr>
                                            <td style='padding:9px 16px;font-size:12.5px;color:#111827;border-bottom:1px solid #f1f5f9;'>
                                                " . e($item['item_name']) . "
                                            </td>
                                            <td style='padding:9px 16px;font-size:12.5px;color:#374151;text-align:center;border-bottom:1px solid #f1f5f9;'>
                                                " . e($item['size']) . "
                                            </td>
                                            <td style='padding:9px 16px;font-size:12.5px;font-weight:600;color:#1d4ed8;text-align:center;border-bottom:1px solid #f1f5f9;'>
                                                " . (int) $item['qty'] . "
                                            </td>
                                        </tr>";
                                }
                    
                                $cardHtml = "
                                    <div style='font-family:\"DM Sans\",system-ui,sans-serif;
                                        border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;
                                        box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:2px;'>
                    
                                        <!-- Employee header -->
                                        <div style='display:flex;align-items:center;gap:12px;
                                            padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>
                                            <div style='width:36px;height:36px;border-radius:50%;background:{$av['bg']};flex-shrink:0;
                                                display:flex;align-items:center;justify-content:center;
                                                font-size:12.5px;font-weight:700;color:{$av['color']};letter-spacing:.02em;'>
                                                {$initials}
                                            </div>
                                            <div style='flex:1;min-width:0;'>
                                                <div style='font-size:13.5px;font-weight:600;color:#111827;
                                                    letter-spacing:-0.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'>
                                                    " . e($employeeName) . "
                                                </div>
                                                <div style='font-size:11px;color:#9ca3af;margin-top:1px;'>
                                                    Employee #{$empIndex}
                                                </div>
                                            </div>
                                            <div style='background:{$av['bg']};color:{$av['color']};
                                                font-size:12.5px;font-weight:700;padding:4px 14px;
                                                border-radius:999px;white-space:nowrap;flex-shrink:0;letter-spacing:-.01em;'>
                                                {$empQty}&nbsp;pc" . ($empQty !== 1 ? 's' : '') . "
                                            </div>
                                        </div>
                    
                                        <!-- Items table -->
                                        <div style='overflow-x:auto;'>
                                            <table style='width:100%;border-collapse:collapse;min-width:300px;'>
                                                <thead>
                                                    <tr style='background:#f1f5f9;'>
                                                        <th style='padding:8px 16px;font-size:10.5px;font-weight:600;color:#64748b;
                                                            text-align:left;text-transform:uppercase;letter-spacing:.07em;'>Item</th>
                                                        <th style='padding:8px 16px;font-size:10.5px;font-weight:600;color:#64748b;
                                                            text-align:center;text-transform:uppercase;letter-spacing:.07em;width:80px;'>Size</th>
                                                        <th style='padding:8px 16px;font-size:10.5px;font-weight:600;color:#64748b;
                                                            text-align:center;text-transform:uppercase;letter-spacing:.07em;width:70px;'>Qty</th>
                                                    </tr>
                                                </thead>
                                                <tbody>{$tableRows}</tbody>
                                                <tfoot>
                                                    <tr style='background:#f0fdf4;border-top:1px solid #bbf7d0;'>
                                                        <td colspan='2' style='padding:8px 16px;font-size:11.5px;
                                                            font-weight:500;color:#6b7280;text-align:right;'>
                                                            Employee total
                                                        </td>
                                                        <td style='padding:8px 16px;font-size:14px;font-weight:700;
                                                            color:#16a34a;text-align:center;letter-spacing:-.02em;'>
                                                            {$empQty}
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>";
                    
                                $fields[] = \Filament\Forms\Components\Placeholder::make('emp_card_' . $empIndex)
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString($cardHtml))
                                    ->columnSpanFull();
                    
                                if ($empIndex < $empCount) {
                                    $fields[] = \Filament\Forms\Components\Placeholder::make('emp_divider_' . $empIndex)
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString(
                                            "<div style='border-top:1px dashed #e2e8f0;margin:10px 0 14px;'></div>"
                                        ))
                                        ->columnSpanFull();
                                }
                            }
                    
                            return $fields;
                        })
                        ->action(function ($record, array $data, Action $action) {
                            if (\App\Models\ForDeliveryReceipt::where('uniform_issuance_id', $record->id)->exists()) {
                                return;
                            }
                    
                            $itemSummary = json_decode($data['item_summary'] ?? '[]', true);
                    
                            if (!is_array($itemSummary) || empty($itemSummary)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('No Items')
                                    ->body('No issued items found to include in the delivery receipt.')
                                    ->warning()
                                    ->send();
                                $action->halt();
                                return;
                            }
                    
                            \App\Models\ForDeliveryReceipt::create([
                                'uniform_issuance_id' => $record->id,
                                'endorse_by'          => $data['endorse_by'],
                                'endorse_date'        => $data['endorse_date'] ?? now()->toDateString(),
                                'item_summary'        => $itemSummary,
                                'status'              => 'pending',
                                'done_date'           => null,
                                'cancel_date'         => null,
                                'remarks'             => $data['remarks'] ?? null,
                            ]);
                    
                            \Filament\Notifications\Notification::make()
                                ->title('Delivery Receipt Created')
                                ->body('A new delivery receipt has been queued successfully.')
                                ->success()
                                ->send();
                        }),
                    
                    
                    // ─── BILLING ──────────────────────────────────────────────────────────────
                    Action::make('billing')
                        ->label('Billing')
                        ->color('warning')
                        ->icon('heroicon-o-banknotes')
                        ->modalWidth('4xl')
                    
                        // ── Visibility: show if issued + has any billable item + not all relievers ──
                        ->visible(function ($record) {
                            if ($record->uniform_issuance_status !== 'issued') return false;
                    
                            $billableTypes = ['new hire', 'additional', 'annual', 'salary deduct'];
                    
                            $record->loadMissing(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                                'uniformIssuanceRecipient.position'
                            );
                    
                            $hasBillableItem = false;
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $typeName = strtolower($item->uniformIssuanceType?->uniform_issuance_type_name ?? '');
                                    foreach ($billableTypes as $t) {
                                        if (str_contains($typeName, $t)) { $hasBillableItem = true; break 3; }
                                    }
                                }
                            }
                            if (! $hasBillableItem) return false;
                    
                            $allRelievers = $record->uniformIssuanceRecipient->every(fn ($r) =>
                                strtolower($r->position?->position_name ?? '') === 'reliever'
                                || strtolower($r->employee_status ?? '') === 'reliever'
                            );
                            if ($allRelievers) return false;
                    
                            // Determine which billing types exist in the items
                            $hasSalaryDeduct    = false;
                            $hasNonSalaryDeduct = false;
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $typeName = strtolower($item->uniformIssuanceType?->uniform_issuance_type_name ?? '');
                                    if (str_contains($typeName, 'salary deduct')) {
                                        $hasSalaryDeduct = true;
                                    } else {
                                        foreach (['new hire', 'additional', 'annual'] as $t) {
                                            if (str_contains($typeName, $t)) { $hasNonSalaryDeduct = true; break; }
                                        }
                                    }
                                }
                            }
                    
                            // Salary deduct: always visible once issued
                            // Client items: need a DR first
                            if ($hasSalaryDeduct) return true;
                            if ($hasNonSalaryDeduct) {
                                return \App\Models\ForDeliveryReceipt::where('uniform_issuance_id', $record->id)->exists();
                            }
                    
                            return false;
                        })
                    
                        // ── Hide submit button only when ALL billing types are already created ──
                        ->modalSubmitAction(function ($action, $record) {
                            $record->loadMissing(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType'
                            );
                    
                            // Collect what billing types this issuance needs
                            $needsClient       = false;
                            $needsSalaryDeduct = false;
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $typeName = strtolower($item->uniformIssuanceType?->uniform_issuance_type_name ?? '');
                                    if (str_contains($typeName, 'salary deduct')) {
                                        $needsSalaryDeduct = true;
                                    } else {
                                        foreach (['new hire', 'additional', 'annual'] as $t) {
                                            if (str_contains($typeName, $t)) { $needsClient = true; break; }
                                        }
                                    }
                                }
                            }
                    
                            $existingTypes = \App\Models\UniformIssuanceBilling::where('uniform_issuance_id', $record->id)
                                ->pluck('billing_type')
                                ->toArray();
                    
                            $clientDone       = ! $needsClient       || in_array('client',        $existingTypes);
                            $salaryDeductDone = ! $needsSalaryDeduct || in_array('salary_deduct', $existingTypes);
                    
                            if ($clientDone && $salaryDeductDone) {
                                return false; // hide submit — everything already billed
                            }
                    
                            return $action;
                        })
                        ->modalCancelActionLabel('Close')
                    
                        // ── Modal content: show existing billings or the issuance header ──────────
                        ->modalContent(function ($record) {
                            $record->load(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                                'uniformIssuanceRecipient.position',
                                'site',
                            );
                    
                            $existingBillings = \App\Models\UniformIssuanceBilling::where('uniform_issuance_id', $record->id)
                                ->latest()
                                ->get();
                    
                            $siteName = e($record->site?->site_name ?? '—');
                            $status   = strtoupper($record->uniform_issuance_status);
                    
                            // Collect distinct issuance type names from items for the header
                            $itemTypeNames = collect();
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $n = $item->uniformIssuanceType?->uniform_issuance_type_name;
                                    if ($n) $itemTypeNames->push($n);
                                }
                            }
                            $typesDisplay = e($itemTypeNames->unique()->implode(', ') ?: '—');
                    
                            $headerHtml = "
                                <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                                    <div style='display:flex;align-items:center;gap:10px;padding:10px 14px;
                                        background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:12px;'>
                                        <div style='width:8px;height:8px;border-radius:50%;background:#d97706;flex-shrink:0;'></div>
                                        <span style='font-size:13px;font-weight:600;color:#1e3a5f;'>{$siteName}</span>
                                        <span style='color:#d1d5db;'>·</span>
                                        <span style='font-size:12.5px;color:#374151;'>{$typesDisplay}</span>
                                        <span style='margin-left:auto;font-size:11px;font-weight:700;color:#1d4ed8;
                                            background:#dbeafe;padding:3px 10px;border-radius:999px;letter-spacing:.03em;'>{$status}</span>
                                    </div>
                                    <div style='font-size:12px;color:#6b7280;line-height:1.6;'>
                                        Fill in billing details below. Items are grouped by billing type (client vs salary deduct).
                                        Both can be billed in a single submission.
                                    </div>
                                </div>";
                    
                            if ($existingBillings->isEmpty()) {
                                return new \Illuminate\Support\HtmlString($headerHtml);
                            }
                    
                            // Show existing billings summary
                            $rows = '';
                            foreach ($existingBillings as $billing) {
                                $statusColor = $billing->status === 'billed' ? '#16a34a' : '#d97706';
                                $statusLabel = strtoupper($billing->status);
                                $billedTo    = e($billing->billed_to);
                                $total       = number_format($billing->total_price, 2);
                                $date        = \Carbon\Carbon::parse($billing->created_at)->timezone('Asia/Manila')->format('M d, Y');
                                $typeLabel   = match ($billing->billing_type) {
                                    'client'        => 'CLIENT',
                                    'salary_deduct' => 'SALARY DEDUCT',
                                    default         => 'OTHER',
                                };
                                $typeBgColor = match ($billing->billing_type) {
                                    'client'        => '#1d4ed8',
                                    'salary_deduct' => '#7c3aed',
                                    default         => '#6b7280',
                                };
                                $rows .= "
                                    <tr>
                                        <td style='padding:10px 14px;font-size:12.5px;font-weight:600;color:#111827;border-bottom:1px solid #f1f5f9;'>{$billedTo}</td>
                                        <td style='padding:10px 14px;font-size:12px;text-align:center;border-bottom:1px solid #f1f5f9;'>
                                            <span style='background:{$typeBgColor};color:#fff;font-size:9.5px;font-weight:700;
                                                padding:3px 9px;border-radius:999px;letter-spacing:.04em;'>{$typeLabel}</span>
                                        </td>
                                        <td style='padding:10px 14px;font-size:13px;color:#1d4ed8;font-weight:700;text-align:right;border-bottom:1px solid #f1f5f9;'>&#x20B1;{$total}</td>
                                        <td style='padding:10px 14px;font-size:11.5px;color:#6b7280;text-align:center;border-bottom:1px solid #f1f5f9;'>{$date}</td>
                                        <td style='padding:10px 14px;text-align:center;border-bottom:1px solid #f1f5f9;'>
                                            <span style='background:{$statusColor};color:#fff;font-size:9.5px;font-weight:700;
                                                padding:3px 9px;border-radius:999px;letter-spacing:.04em;'>{$statusLabel}</span>
                                        </td>
                                    </tr>";
                            }
                    
                            $grandTotal = number_format($existingBillings->sum('total_price'), 2);
                    
                            $existingTable = "
                                <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                                    <div style='font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;
                                        letter-spacing:.08em;margin-bottom:10px;'>
                                        Existing Billings &nbsp;({$existingBillings->count()})
                                    </div>
                                    <div style='border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;
                                        box-shadow:0 1px 4px rgba(0,0,0,.05);margin-bottom:12px;'>
                                        <table style='width:100%;border-collapse:collapse;'>
                                            <thead>
                                                <tr style='background:#1e3a5f;'>
                                                    <th style='padding:10px 14px;text-align:left;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Billed To</th>
                                                    <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Type</th>
                                                    <th style='padding:10px 14px;text-align:right;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Total</th>
                                                    <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Date</th>
                                                    <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:600;color:#e0f2fe;text-transform:uppercase;letter-spacing:.07em;'>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>{$rows}</tbody>
                                            <tfoot>
                                                <tr style='background:#f0f9ff;border-top:2px solid #93c5fd;'>
                                                    <td colspan='2' style='padding:10px 14px;font-size:11.5px;font-weight:600;color:#374151;text-align:right;'>Grand Total</td>
                                                    <td style='padding:10px 14px;font-size:15px;font-weight:800;color:#1d4ed8;text-align:right;letter-spacing:-0.03em;'>&#x20B1;{$grandTotal}</td>
                                                    <td colspan='2'></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>";
                    
                            // Check if there are still unbilled types to show the form header too
                            $existingTypes = $existingBillings->pluck('billing_type')->toArray();
                            $stillHasMore  = ! in_array('client', $existingTypes) || ! in_array('salary_deduct', $existingTypes);
                    
                            return new \Illuminate\Support\HtmlString(
                                $existingTable . ($stillHasMore ? $headerHtml : "
                                    <div style='padding:10px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:8px;
                                        font-size:12px;color:#854d0e;text-align:center;font-weight:500;'>
                                        &#9432;&nbsp; All billing types for this issuance have already been created.
                                    </div>")
                            );
                        })
                    
                        // ── Form: build fields based on per-item billing types ────────────────────
                        ->form(function ($record) {
                            $record->load(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                                'uniformIssuanceRecipient.position',
                                'site',
                            );
                    
                            // Determine which billing types are already done
                            $existingTypes = \App\Models\UniformIssuanceBilling::where('uniform_issuance_id', $record->id)
                                ->pluck('billing_type')
                                ->toArray();
                    
                            $billableClientTypes     = ['new hire', 'additional', 'annual'];
                            $billableSalaryTypes     = ['salary deduct'];
                    
                            // ── Group items by billing type and employee ──────────────────────────
                            // $clientGrouped      = [employeeName => [items...]]
                            // $salaryGrouped      = [employeeName => [items...]]
                            $clientGrouped   = [];
                            $salaryGrouped   = [];
                            $recipientMeta   = [];
                    
                            foreach ($record->uniformIssuanceRecipient as $recipient) {
                                $employeeName = $recipient->employee_name ?? '—';
                                $empStatus    = strtolower($recipient->employee_status ?? '');
                                $isReliever   = strtolower($recipient->position?->position_name ?? '') === 'reliever'
                                            || $empStatus === 'reliever';
                    
                                $recipientMeta[$employeeName] = ['employee_status' => $empStatus, 'is_reliever' => $isReliever];
                    
                                foreach ($recipient->uniformIssuanceItem as $item) {
                                    $qty = (int) $item->released_quantity;
                                    if ($qty <= 0) continue;
                    
                                    $itemTypeName = strtolower($item->uniformIssuanceType?->uniform_issuance_type_name ?? '');
                    
                                    $isSalaryDeductItem = false;
                                    foreach ($billableSalaryTypes as $t) {
                                        if (str_contains($itemTypeName, $t)) { $isSalaryDeductItem = true; break; }
                                    }
                    
                                    $isClientItem = false;
                                    if (! $isSalaryDeductItem) {
                                        foreach ($billableClientTypes as $t) {
                                            if (str_contains($itemTypeName, $t)) { $isClientItem = true; break; }
                                        }
                                    }
                    
                                    if (! $isSalaryDeductItem && ! $isClientItem) continue; // non-billable type
                                    if (! $isSalaryDeductItem && $isReliever) continue;     // relievers excluded from client
                    
                                    $key = $item->uniform_item_id . '_' . $item->uniform_item_variant_id . '_' . $item->uniform_issuance_type_id;
                    
                                    $rowData = [
                                        'employee'           => $employeeName,
                                        'item_name'          => $item->uniformItem?->uniform_item_name ?? '—',
                                        'size'               => $item->uniformItemVariant?->uniform_item_size ?? '—',
                                        'quantity'           => 0,
                                        'unit_price'         => (float) ($item->uniformItem?->uniform_item_price ?? 0),
                                        'issuance_type_name' => $item->uniformIssuanceType?->uniform_issuance_type_name ?? '—',
                                        'billing_type'       => $isSalaryDeductItem ? 'salary_deduct' : 'client',
                                    ];
                    
                                    if ($isSalaryDeductItem) {
                                        if (! isset($salaryGrouped[$employeeName][$key])) {
                                            $salaryGrouped[$employeeName][$key] = $rowData;
                                        }
                                        $salaryGrouped[$employeeName][$key]['quantity'] += $qty;
                                    } else {
                                        if (! isset($clientGrouped[$employeeName][$key])) {
                                            $clientGrouped[$employeeName][$key] = $rowData;
                                        }
                                        $clientGrouped[$employeeName][$key]['quantity'] += $qty;
                                    }
                                }
                            }
                    
                            // Re-index to flat arrays per employee
                            $clientGrouped = array_map(fn ($items) => array_values($items), array_filter($clientGrouped, fn ($i) => ! empty($i)));
                            $salaryGrouped = array_map(fn ($items) => array_values($items), array_filter($salaryGrouped, fn ($i) => ! empty($i)));
                    
                            $hasClientItems  = ! empty($clientGrouped) && ! in_array('client', $existingTypes);
                            $hasSalaryItems  = ! empty($salaryGrouped) && ! in_array('salary_deduct', $existingTypes);
                    
                            if (! $hasClientItems && ! $hasSalaryItems) return []; // nothing left to bill
                    
                            // Flatten for hidden JSON fields
                            $clientItemsFlat = [];
                            foreach ($clientGrouped as $empItems) foreach ($empItems as $row) $clientItemsFlat[] = $row;
                    
                            $salaryItemsFlat = [];
                            foreach ($salaryGrouped as $empItems) foreach ($empItems as $row) $salaryItemsFlat[] = $row;
                    
                            $computeTotal = fn (array $items) => array_sum(
                                array_map(fn ($i) => (float) ($i['unit_price'] ?? 0) * (int) ($i['quantity'] ?? 0), $items)
                            );
                    
                            $avatarStyles = [
                                ['bg' => '#E6F1FB', 'color' => '#0C447C'],
                                ['bg' => '#E1F5EE', 'color' => '#0F6E56'],
                                ['bg' => '#EEEDFE', 'color' => '#3C3489'],
                                ['bg' => '#FAEEDA', 'color' => '#633806'],
                                ['bg' => '#FAECE7', 'color' => '#712B13'],
                            ];
                    
                            $fields = [];
                    
                            // ── Helper: build one employee card ──────────────────────────────────
                            $buildEmpCard = function (
                                string $employeeName,
                                array  $empItems,
                                int    $empIndex,
                                array  $avatarStyles,
                                array  $recipientMeta,
                                bool   $showType = true
                            ): string {
                                $av       = $avatarStyles[($empIndex - 1) % count($avatarStyles)];
                                $empTotal = array_sum(array_map(
                                    fn ($i) => (float) ($i['unit_price'] ?? 0) * (int) ($i['quantity'] ?? 0),
                                    $empItems
                                ));
                                $words    = explode(' ', trim($employeeName));
                                $initials = strtoupper(
                                    (isset($words[0]) ? substr($words[0], 0, 1) : '') .
                                    (isset($words[1]) ? substr($words[1], 0, 1) : '')
                                );
                                $empStatus = $recipientMeta[$employeeName]['employee_status'] ?? '';
                                $statusBadgeHtml = '';
                                if ($empStatus) {
                                    $statusBadgeColor = match ($empStatus) {
                                        'posted'       => '#7c3aed',
                                        'reliever'     => '#d97706',
                                        'probationary' => '#2563eb',
                                        'contractual'  => '#0d9488',
                                        default        => '#6b7280',
                                    };
                                    $statusBadgeHtml = "<span style='background:{$statusBadgeColor};color:#fff;font-size:9.5px;
                                        font-weight:700;padding:2px 9px;border-radius:999px;margin-left:8px;letter-spacing:.04em;'>
                                        " . strtoupper($empStatus) . "</span>";
                                }
                    
                                $tableRows = '';
                                foreach ($empItems as $item) {
                                    $sub = (float) ($item['unit_price'] ?? 0) * (int) ($item['quantity'] ?? 0);
                                    $typeTag = $showType
                                        ? "<span style='font-size:9px;background:#e0e7ff;color:#4338ca;padding:1px 6px;border-radius:999px;margin-left:4px;font-weight:600;'>"
                                        . e($item['issuance_type_name'] ?? '') . "</span>"
                                        : '';
                                    $tableRows .= "
                                        <tr>
                                            <td style='padding:8px 14px;font-size:12.5px;color:#111827;border-bottom:1px solid #f1f5f9;'>
                                                " . e($item['item_name']) . " {$typeTag}
                                            </td>
                                            <td style='padding:8px 14px;font-size:12.5px;color:#374151;text-align:center;border-bottom:1px solid #f1f5f9;'>" . e($item['size']) . "</td>
                                            <td style='padding:8px 14px;font-size:12.5px;font-weight:600;color:#1d4ed8;text-align:center;border-bottom:1px solid #f1f5f9;'>" . (int) $item['quantity'] . "</td>
                                            <td style='padding:8px 14px;font-size:12.5px;color:#374151;text-align:right;border-bottom:1px solid #f1f5f9;'>&#x20B1;" . number_format((float) $item['unit_price'], 2) . "</td>
                                            <td style='padding:8px 14px;font-size:12.5px;font-weight:600;color:#111827;text-align:right;border-bottom:1px solid #f1f5f9;'>&#x20B1;" . number_format($sub, 2) . "</td>
                                        </tr>";
                                }
                    
                                return "
                                    <div style='font-family:\"DM Sans\",system-ui,sans-serif;border:1px solid #e2e8f0;border-radius:12px;
                                        overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.05);'>
                                        <div style='display:flex;align-items:center;gap:12px;padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>
                                            <div style='width:36px;height:36px;border-radius:50%;background:{$av['bg']};flex-shrink:0;
                                                display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700;color:{$av['color']};'>
                                                {$initials}
                                            </div>
                                            <div style='flex:1;min-width:0;'>
                                                <div style='font-size:13.5px;font-weight:600;color:#111827;display:flex;align-items:center;flex-wrap:wrap;gap:4px;'>
                                                    " . e($employeeName) . " {$statusBadgeHtml}
                                                </div>
                                                <div style='font-size:11px;color:#9ca3af;margin-top:2px;'>Employee #{$empIndex}</div>
                                            </div>
                                            <div style='background:{$av['bg']};color:{$av['color']};font-size:13px;font-weight:700;
                                                padding:4px 14px;border-radius:999px;white-space:nowrap;flex-shrink:0;'>
                                                &#x20B1;" . number_format($empTotal, 2) . "
                                            </div>
                                        </div>
                                        <div style='overflow-x:auto;'>
                                            <table style='width:100%;border-collapse:collapse;min-width:400px;'>
                                                <thead>
                                                    <tr style='background:#f1f5f9;'>
                                                        <th style='padding:8px 14px;font-size:10.5px;font-weight:600;color:#64748b;text-align:left;text-transform:uppercase;letter-spacing:.07em;'>Item</th>
                                                        <th style='padding:8px 14px;font-size:10.5px;font-weight:600;color:#64748b;text-align:center;text-transform:uppercase;letter-spacing:.07em;width:65px;'>Size</th>
                                                        <th style='padding:8px 14px;font-size:10.5px;font-weight:600;color:#64748b;text-align:center;text-transform:uppercase;letter-spacing:.07em;width:55px;'>Qty</th>
                                                        <th style='padding:8px 14px;font-size:10.5px;font-weight:600;color:#64748b;text-align:right;text-transform:uppercase;letter-spacing:.07em;width:110px;'>Unit Price</th>
                                                        <th style='padding:8px 14px;font-size:10.5px;font-weight:600;color:#64748b;text-align:right;text-transform:uppercase;letter-spacing:.07em;width:100px;'>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>{$tableRows}</tbody>
                                                <tfoot>
                                                    <tr style='background:#f0f9ff;border-top:1px solid #bfdbfe;'>
                                                        <td colspan='4' style='padding:8px 14px;font-size:11.5px;font-weight:500;color:#6b7280;text-align:right;'>Employee total</td>
                                                        <td style='padding:8px 14px;font-size:14px;font-weight:700;color:#1d4ed8;text-align:right;'>
                                                            &#x20B1;" . number_format($empTotal, 2) . "
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>";
                            };
                    
                            // ═══════════════════════════════════════════════════════════════════
                            // SECTION A — CLIENT BILLING
                            // ═══════════════════════════════════════════════════════════════════
                            if ($hasClientItems) {
                                $clientTotal    = $computeTotal($clientItemsFlat);
                                $clientEmpCount = count($clientGrouped);
                    
                                // Does any posted employee exist?
                                $hasPostedClient = false;
                                foreach ($clientGrouped as $empName => $_) {
                                    if (($recipientMeta[$empName]['employee_status'] ?? '') === 'posted') {
                                        $hasPostedClient = true;
                                        break;
                                    }
                                }
                    
                                $fields[] = \Filament\Forms\Components\Placeholder::make('client_section_header')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString("
                                        <div style='font-family:\"DM Sans\",system-ui,sans-serif;padding:11px 15px;
                                            background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-top:8px;'>
                                            <div style='font-size:13px;font-weight:700;color:#1e3a5f;margin-bottom:4px;'>
                                                🏢 Client Billing
                                            </div>
                                            <div style='font-size:12px;color:#374151;line-height:1.6;'>
                                                One billing record will be created for the client covering
                                                <strong>{$clientEmpCount} employee" . ($clientEmpCount !== 1 ? 's' : '') . "</strong>
                                                — total <strong>&#x20B1;" . number_format($clientTotal, 2) . "</strong>.
                                                " . ($hasPostedClient ? "<br><span style='color:#7c3aed;font-size:11.5px;'>
                                                    &#9432; Posted employees require a signed DR upload.
                                                </span>" : '') . "
                                            </div>
                                        </div>
                                    "))
                                    ->columnSpanFull();
                    
                                $fields[] = \Filament\Forms\Components\TextInput::make('client_billed_to')
                                    ->label('Billed To (Client)')
                                    ->default($record->site?->site_name ?? '')
                                    ->required()
                                    ->columnSpanFull();
                    
                                $fields[] = \Filament\Forms\Components\Hidden::make('client_billing_items')
                                    ->default(json_encode($clientItemsFlat, JSON_UNESCAPED_UNICODE));
                    
                                // Summary strip
                                $fields[] = \Filament\Forms\Components\Placeholder::make('client_summary_strip')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString("
                                        <div style='display:flex;align-items:center;justify-content:space-between;
                                            padding:8px 0;border-bottom:1.5px solid #e5e7eb;margin-top:4px;'>
                                            <span style='font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.08em;'>
                                                {$clientEmpCount} employee" . ($clientEmpCount !== 1 ? 's' : '') . "
                                            </span>
                                            <span style='font-size:14px;font-weight:700;color:#1d4ed8;'>
                                                &#x20B1;" . number_format($clientTotal, 2) . "
                                            </span>
                                        </div>
                                    "))
                                    ->columnSpanFull();
                    
                                // Per-employee cards + optional DR upload for posted employees
                                $empIndex = 0;
                                $clientEmpNames = array_keys($clientGrouped);
                                foreach ($clientGrouped as $employeeName => $empItems) {
                                    $empIndex++;
                                    $safeKey   = 'client_emp_' . $empIndex;
                                    $isPosted  = ($recipientMeta[$employeeName]['employee_status'] ?? '') === 'posted';
                    
                                    $uploadSectionHtml = '';
                                    if ($isPosted) {
                                        $uploadSectionHtml = "
                                            <div style='padding:12px 16px 4px;border-top:1px solid #ede9fe;background:#faf5ff;'>
                                                <div style='font-size:11.5px;font-weight:700;color:#7c3aed;text-transform:uppercase;
                                                    letter-spacing:.06em;margin-bottom:4px;'>&#9432; Signed DR Required</div>
                                                <div style='font-size:11.5px;color:#6b7280;'>
                                                    Upload signed delivery receipt for <strong>" . e($employeeName) . "</strong>
                                                </div>
                                            </div>";
                                    }
                    
                                    $cardHtml = $buildEmpCard($employeeName, $empItems, $empIndex, $avatarStyles, $recipientMeta, true);
                                    // Inject upload section into bottom of card
                                    $cardHtml = str_replace('</div>', $uploadSectionHtml . '</div>', $cardHtml);
                    
                                    $fields[] = \Filament\Forms\Components\Placeholder::make('client_card_' . $empIndex)
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString($cardHtml))
                                        ->columnSpanFull();
                    
                                    if ($isPosted) {
                                        $fields[] = \Filament\Forms\Components\TextInput::make("dr_number_{$safeKey}")
                                            ->label('DR Number for ' . $employeeName)
                                            ->placeholder('e.g. DR-2024-001')
                                            ->required();
                    
                                        $fields[] = \Filament\Forms\Components\FileUpload::make("dr_image_{$safeKey}")
                                            ->label('Signed DR Image')
                                            ->image()
                                            ->imagePreviewHeight('130')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                            ->directory('billing-dr')
                                            ->required()
                                            ->columnSpanFull();
                    
                                        $fields[] = \Filament\Forms\Components\DatePicker::make("dr_date_signed_{$safeKey}")
                                            ->label('DR Date Signed')
                                            ->default(now()->toDateString())
                                            ->required();
                    
                                        $fields[] = \Filament\Forms\Components\TextInput::make("dr_remarks_{$safeKey}")
                                            ->label('DR Remarks')
                                            ->placeholder('Optional');
                                    }
                    
                                    if ($empIndex < $clientEmpCount) {
                                        $fields[] = \Filament\Forms\Components\Placeholder::make('client_div_' . $empIndex)
                                            ->label('')
                                            ->content(new \Illuminate\Support\HtmlString("<div style='border-top:1px dashed #e2e8f0;margin:12px 0 14px;'></div>"))
                                            ->columnSpanFull();
                                    }
                                }
                            }
                    
                            // ═══════════════════════════════════════════════════════════════════
                            // SECTION B — SALARY DEDUCT BILLING
                            // ═══════════════════════════════════════════════════════════════════
                            if ($hasSalaryItems) {
                                $salaryTotal    = $computeTotal($salaryItemsFlat);
                                $salaryEmpCount = count($salaryGrouped);
                    
                                // Divider between sections
                                if ($hasClientItems) {
                                    $fields[] = \Filament\Forms\Components\Placeholder::make('section_divider')
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString(
                                            "<div style='border-top:2px dashed #e5e7eb;margin:20px 0 16px;'></div>"
                                        ))
                                        ->columnSpanFull();
                                }
                    
                                $fields[] = \Filament\Forms\Components\Placeholder::make('salary_section_header')
                                    ->label('')
                                    ->content(new \Illuminate\Support\HtmlString("
                                        <div style='font-family:\"DM Sans\",system-ui,sans-serif;padding:11px 15px;
                                            background:#f5f3ff;border:1px solid #ddd6fe;border-radius:10px;'>
                                            <div style='font-size:13px;font-weight:700;color:#4c1d95;margin-bottom:4px;'>
                                                💳 Salary Deduct Billing
                                            </div>
                                            <div style='font-size:12px;color:#374151;line-height:1.6;'>
                                                One billing record per employee will be created — <strong>{$salaryEmpCount} record" . ($salaryEmpCount !== 1 ? 's' : '') . "</strong>
                                                totalling <strong>&#x20B1;" . number_format($salaryTotal, 2) . "</strong>.
                                                <br><span style='color:#7c3aed;font-size:11.5px;'>
                                                    &#9432; A signed ATD upload is required for each employee.
                                                </span>
                                            </div>
                                        </div>
                                    "))
                                    ->columnSpanFull();
                    
                                $fields[] = \Filament\Forms\Components\Hidden::make('salary_billing_items')
                                    ->default(json_encode($salaryItemsFlat, JSON_UNESCAPED_UNICODE));
                    
                                $empIndex = 0;
                                $salaryEmpNames = array_keys($salaryGrouped);
                                foreach ($salaryGrouped as $employeeName => $empItems) {
                                    $empIndex++;
                                    $safeKey = 'salary_emp_' . $empIndex;
                    
                                    $uploadSectionHtml = "
                                        <div style='padding:12px 16px 4px;border-top:1px solid #ede9fe;background:#faf5ff;'>
                                            <div style='font-size:11.5px;font-weight:700;color:#7c3aed;text-transform:uppercase;
                                                letter-spacing:.06em;margin-bottom:4px;'>&#9432; Signed ATD Required</div>
                                            <div style='font-size:11.5px;color:#6b7280;'>
                                                Upload acknowledgement for <strong>" . e($employeeName) . "</strong>
                                            </div>
                                        </div>";
                    
                                    $cardHtml = $buildEmpCard($employeeName, $empItems, $empIndex, $avatarStyles, $recipientMeta, true);
                                    $cardHtml = str_replace('</div>', $uploadSectionHtml . '</div>', $cardHtml);
                    
                                    $fields[] = \Filament\Forms\Components\Placeholder::make('salary_card_' . $empIndex)
                                        ->label('')
                                        ->content(new \Illuminate\Support\HtmlString($cardHtml))
                                        ->columnSpanFull();
                    
                                    $fields[] = \Filament\Forms\Components\FileUpload::make("atd_image_{$safeKey}")
                                        ->label('ATD Document for ' . $employeeName)
                                        ->image()
                                        ->imagePreviewHeight('130')
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                        ->directory('billing-atd')
                                        ->required()
                                        ->columnSpanFull();
                    
                                    $fields[] = \Filament\Forms\Components\DatePicker::make("atd_date_signed_{$safeKey}")
                                        ->label('ATD Date Signed')
                                        ->default(now()->toDateString())
                                        ->required();
                    
                                    $fields[] = \Filament\Forms\Components\TextInput::make("atd_remarks_{$safeKey}")
                                        ->label('ATD Remarks')
                                        ->placeholder('Optional');
                    
                                    if ($empIndex < $salaryEmpCount) {
                                        $fields[] = \Filament\Forms\Components\Placeholder::make('salary_div_' . $empIndex)
                                            ->label('')
                                            ->content(new \Illuminate\Support\HtmlString("<div style='border-top:1px dashed #e2e8f0;margin:12px 0 14px;'></div>"))
                                            ->columnSpanFull();
                                    }
                                }
                            }
                    
                            return $fields;
                        })
                    
                        // ── Action: create billing records per type ────────────────────────────────
                        ->action(function ($record, array $data, Action $action) {
                            $record->load(
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                'uniformIssuanceRecipient.uniformIssuanceItem.uniformIssuanceType',
                                'uniformIssuanceRecipient.position',
                                'site',
                            );
                    
                            $existingTypes = \App\Models\UniformIssuanceBilling::where('uniform_issuance_id', $record->id)
                                ->pluck('billing_type')
                                ->toArray();
                    
                            $computeTotal = fn (array $items) => array_sum(
                                array_map(fn ($i) => (float) ($i['unit_price'] ?? 0) * (int) ($i['quantity'] ?? 0), $items)
                            );
                    
                            $created = [];
                    
                            // ── CLIENT BILLING ────────────────────────────────────────────────
                            $clientItemsRaw = $data['client_billing_items'] ?? null;
                            if ($clientItemsRaw && ! in_array('client', $existingTypes)) {
                                $clientItems = json_decode($clientItemsRaw, true);
                    
                                if (is_array($clientItems) && ! empty($clientItems)) {
                                    $total = $computeTotal($clientItems);
                    
                                    $billing = \App\Models\UniformIssuanceBilling::create([
                                        'uniform_issuance_id'   => $record->id,
                                        'billed_to'             => $data['client_billed_to'] ?? $record->site?->site_name ?? '—',
                                        'billing_type'          => 'client',
                                        'billing_items'         => $clientItems,
                                        'employee_attachments'  => null,
                                        'total_price'           => $total,
                                        'status'                => 'pending',
                                        'billed_at'             => null,
                                        'created_by'            => \Illuminate\Support\Facades\Auth::id(),
                                        'signed_receiving_copy' => null,
                                    ]);
                    
                                    // DR uploads for posted employees
                                    // Rebuild employee index map for client group
                                    $clientEmpIndex = 0;
                                    $seenEmps = [];
                                    foreach ($clientItems as $item) {
                                        $empName = $item['employee'] ?? '';
                                        if ($empName && ! isset($seenEmps[$empName])) {
                                            $seenEmps[$empName] = true;
                                            $clientEmpIndex++;
                    
                                            $empStatus = '';
                                            foreach ($record->uniformIssuanceRecipient as $r) {
                                                if ($r->employee_name === $empName) {
                                                    $empStatus = strtolower($r->employee_status ?? '');
                                                    break;
                                                }
                                            }
                    
                                            if ($empStatus === 'posted') {
                                                $safeKey = 'client_emp_' . $clientEmpIndex;
                                                \App\Models\BillingDr::create([
                                                    'billable_id'     => $billing->id,
                                                    'billable_type'   => \App\Models\UniformIssuanceBilling::class,
                                                    'sourceable_id'   => $record->id,
                                                    'sourceable_type' => \App\Models\UniformIssuances::class,
                                                    'employee_name'   => $empName,
                                                    'dr_number'       => $data["dr_number_{$safeKey}"]      ?? '—',
                                                    'date_signed'     => $data["dr_date_signed_{$safeKey}"] ?? null,
                                                    'dr_image'        => $data["dr_image_{$safeKey}"]       ?? null,
                                                    'remarks'         => $data["dr_remarks_{$safeKey}"]     ?? null,
                                                    'uploaded_by'     => \Illuminate\Support\Facades\Auth::id(),
                                                ]);
                                            }
                                        }
                                    }
                    
                                    $created[] = 'Client billing ₱' . number_format($total, 2);
                                }
                            }
                    
                            // ── SALARY DEDUCT BILLING ─────────────────────────────────────────
                            $salaryItemsRaw = $data['salary_billing_items'] ?? null;
                            if ($salaryItemsRaw && ! in_array('salary_deduct', $existingTypes)) {
                                $salaryItems = json_decode($salaryItemsRaw, true);
                    
                                if (is_array($salaryItems) && ! empty($salaryItems)) {
                                    // Group by employee
                                    $salaryGrouped = [];
                                    foreach ($salaryItems as $item) {
                                        $emp = $item['employee'] ?? '—';
                                        $salaryGrouped[$emp][] = $item;
                                    }
                    
                                    $salaryEmpIndex = 0;
                                    foreach ($salaryGrouped as $employeeName => $items) {
                                        $salaryEmpIndex++;
                                        $safeKey = 'salary_emp_' . $salaryEmpIndex;
                                        $total   = $computeTotal($items);
                    
                                        $billing = \App\Models\UniformIssuanceBilling::create([
                                            'uniform_issuance_id'   => $record->id,
                                            'billed_to'             => $employeeName,
                                            'billing_type'          => 'salary_deduct',
                                            'billing_items'         => $items,
                                            'employee_attachments'  => null,
                                            'total_price'           => $total,
                                            'status'                => 'pending',
                                            'billed_at'             => null,
                                            'created_by'            => \Illuminate\Support\Facades\Auth::id(),
                                            'signed_receiving_copy' => null,
                                        ]);
                    
                                        \App\Models\BillingAtd::create([
                                            'uniform_issuance_id'         => $record->id,
                                            'uniform_issuance_billing_id' => $billing->id,
                                            'employee_name'               => $employeeName,
                                            'date_signed'                 => $data["atd_date_signed_{$safeKey}"] ?? null,
                                            'atd_image'                   => $data["atd_image_{$safeKey}"]       ?? null,
                                            'remarks'                     => $data["atd_remarks_{$safeKey}"]     ?? null,
                                            'uploaded_by'                 => \Illuminate\Support\Facades\Auth::id(),
                                        ]);
                                    }
                    
                                    $created[] = count($salaryGrouped) . ' salary deduct billing(s) ₱' . number_format($computeTotal($salaryItems), 2);
                                }
                            }
                    
                            if (empty($created)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Nothing to Bill')
                                    ->body('No new billing records were created.')
                                    ->warning()
                                    ->send();
                                return;
                            }
                    
                            \Filament\Notifications\Notification::make()
                                ->title('Billing Created')
                                ->body(implode(' · ', $created))
                                ->success()
                                ->send();
                        }),

                        // ─── EDIT ─────────────────────────────────────────────────────────────
                        Action::make('edit')
                            ->label('Edit')
                            ->icon('heroicon-o-pencil-square')
                            ->color('primary')
                            ->visible(fn ($record) => $record->uniform_issuance_status === 'pending')
                            ->modalHeading('Edit Issuance')
                            ->modalSubmitActionLabel('Save Changes')
                            ->modalWidth('5xl')
                            ->form(fn (\Filament\Schemas\Schema $schema) => UniformIssuancesForm::configure($schema))
                            ->fillForm(function ($record): array {
                                $record->loadMissing(
                                    'uniformIssuanceRecipient.uniformIssuanceItem',
                                    'uniformIssuanceRecipient.position',
                                );

                                return [
                                    'site_id'                    => $record->site_id,
                                    'uniform_issuance_status'    => $record->uniform_issuance_status,
                                    'pending_at'                 => $record->pending_at?->toDateString(),
                                    'issued_at'                  => $record->issued_at?->toDateString(),
                                    'notes'                      => $record->notes,
                                    'uniformIssuanceRecipient'   => $record->uniformIssuanceRecipient->map(fn ($recipient) => [
                                        'id'                     => $recipient->id,
                                        'transaction_id'         => $recipient->transaction_id,
                                        'employee_name'          => $recipient->employee_name,
                                        'employee_status'        => $recipient->employee_status,
                                        'position_id'            => $recipient->position_id,
                                        'uniform_set_id'         => $recipient->uniform_set_id,
                                        'uniformIssuanceItem'    => $recipient->uniformIssuanceItem->map(fn ($item) => [
                                            'id'                         => $item->id,
                                            'uniform_issuance_type_id'   => $item->uniform_issuance_type_id,
                                            'uniform_item_id'            => $item->uniform_item_id,
                                            'uniform_item_variant_id'    => $item->uniform_item_variant_id,
                                            'quantity'                   => $item->quantity,
                                            'released_quantity'          => $item->released_quantity,
                                            'remaining_quantity'         => $item->remaining_quantity,
                                        ])->toArray(),
                                    ])->toArray(),
                                ];
                            })
                            ->action(function ($record, array $data, Action $action): void {
                                $status = $data['uniform_issuance_status'] ?? 'pending';

                                // ── Aggregate stock issues ─────────────────────────────────────
                                $aggregate = [];
                                foreach ($data['uniformIssuanceRecipient'] ?? [] as $recipient) {
                                    foreach ($recipient['uniformIssuanceItem'] ?? [] as $item) {
                                        $variantId = $item['uniform_item_variant_id'] ?? null;
                                        $qty       = (int) ($item['quantity'] ?? 0);
                                        if (!$variantId || $qty <= 0) continue;
                                        $aggregate[$variantId] = ($aggregate[$variantId] ?? 0) + $qty;
                                    }
                                }

                                $issues = [];
                                foreach ($aggregate as $variantId => $totalOrdered) {
                                    $variant = \App\Models\UniformItemVariants::find($variantId);
                                    if (!$variant) continue;
                                    $stock = (int) $variant->uniform_item_quantity;
                                    if ($totalOrdered > $stock) {
                                        $issues[] = [
                                            'variant'   => $variant->uniform_item_size,
                                            'ordered'   => $totalOrdered,
                                            'stock'     => $stock,
                                            'shortfall' => $totalOrdered - $stock,
                                        ];
                                    }
                                }

                                // ── HARD BLOCK: issued + insufficient stock ────────────────────
                                if ($status === 'issued' && !empty($issues)) {
                                    $lines = collect($issues)
                                        ->map(fn ($i) => "• {$i['variant']}: ordered {$i['ordered']}, in stock {$i['stock']} (short by {$i['shortfall']})")
                                        ->implode("\n");

                                    Notification::make()
                                        ->title('⛔ Cannot Issue — Insufficient Stock')
                                        ->body(new \Illuminate\Support\HtmlString("
                                            <div style='font-size:12px;color:#7f1d1d;margin-bottom:6px;'>
                                                Reduce quantities or change status to <strong>Pending</strong>.
                                            </div>
                                            <pre style='font-size:11px;color:#991b1b;white-space:pre-wrap;margin:0;'>{$lines}</pre>
                                        "))
                                        ->danger()
                                        ->persistent()
                                        ->send();

                                    $action->halt();
                                    return;
                                }

                                // ── SOFT BLOCK: pending + stock issues + not acknowledged ──────
                                if ($status === 'pending' && !empty($issues)) {
                                    $acknowledged = (bool) ($data['stock_warning_acknowledged'] ?? false);

                                    if (!$acknowledged) {
                                        Notification::make()
                                            ->title('⚠️ Stock Warning — Confirmation Required')
                                            ->body('Some items exceed available stock. Tick the confirmation toggle before saving.')
                                            ->warning()
                                            ->persistent()
                                            ->send();

                                        $action->halt();
                                        return;
                                    }
                                }

                                // ── SAVE ──────────────────────────────────────────────────────
                                DB::transaction(function () use ($record, $data): void {
                                    $record->update(
                                        collect($data)
                                            ->except(['uniformIssuanceRecipient', 'stock_warning_acknowledged'])
                                            ->toArray()
                                    );

                                    $incomingRecipientIds = [];

                                    foreach ($data['uniformIssuanceRecipient'] ?? [] as $recipientData) {
                                        $recipientId = $recipientData['id'] ?? null;

                                        $recipientRecord = $recipientId
                                            ? $record->uniformIssuanceRecipient()->find($recipientId)
                                            : null;

                                        $recipientPayload = collect($recipientData)
                                            ->except(['id', 'uniformIssuanceItem'])
                                            ->toArray();

                                        if ($recipientRecord) {
                                            $recipientRecord->update($recipientPayload);
                                        } else {
                                            $recipientRecord = $record->uniformIssuanceRecipient()->create($recipientPayload);
                                        }

                                        $incomingRecipientIds[] = $recipientRecord->id;
                                        $incomingItemIds = [];

                                        foreach ($recipientData['uniformIssuanceItem'] ?? [] as $itemData) {
                                            $itemId = $itemData['id'] ?? null;

                                            $itemRecord = $itemId
                                                ? $recipientRecord->uniformIssuanceItem()->find($itemId)
                                                : null;

                                            $qty      = (int) ($itemData['quantity'] ?? 0);
                                            $released = (int) ($itemData['released_quantity'] ?? 0);

                                            $itemPayload = [
                                                'uniform_issuance_type_id' => $itemData['uniform_issuance_type_id'] ?? null,
                                                'uniform_item_id'          => $itemData['uniform_item_id'] ?? null,
                                                'uniform_item_variant_id'  => $itemData['uniform_item_variant_id'] ?? null,
                                                'quantity'                 => $qty,
                                                'released_quantity'        => $released,
                                                'remaining_quantity'       => max(0, $qty - $released),
                                            ];

                                            if ($itemRecord) {
                                                $itemRecord->update($itemPayload);
                                            } else {
                                                $itemRecord = $recipientRecord->uniformIssuanceItem()->create($itemPayload);
                                            }

                                            $incomingItemIds[] = $itemRecord->id;
                                        }

                                        // Delete removed items
                                        $recipientRecord->uniformIssuanceItem()
                                            ->whereNotIn('id', $incomingItemIds)
                                            ->delete();
                                    }

                                    // Delete removed recipients (and cascade their items)
                                    $record->uniformIssuanceRecipient()
                                        ->whereNotIn('id', $incomingRecipientIds)
                                        ->each(function ($r) {
                                            $r->uniformIssuanceItem()->delete();
                                            $r->delete();
                                        });

                                    \App\Models\UniformIssuanceLog::create([
                                        'uniform_issuance_id' => $record->id,
                                        'user_id'             => \Illuminate\Support\Facades\Auth::id(),
                                        'action'              => 'edited',
                                        'status_from'         => $record->getOriginal('uniform_issuance_status'),
                                        'status_to'           => $data['uniform_issuance_status'] ?? $record->uniform_issuance_status,
                                        'note'                => 'Issuance was edited.',
                                    ]);
                                });

                                Notification::make()
                                    ->title('Issuance updated')
                                    ->success()
                                    ->send();
                            }),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('bulk_print_receiving_copy')
                        ->label('Print Receiving Copies')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $eligible = $records->filter(
                                fn ($r) => in_array($r->uniform_issuance_status, ['partial', 'issued'])
                            );

                            if ($eligible->isEmpty()) {
                                Notification::make()
                                    ->title('No eligible records')
                                    ->body('Bulk print only works for partial or issued issuances.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $ids = $eligible->pluck('id')->implode(',');
                            $url = route('uniform-issuances.bulk.receiving-copy', ['ids' => $ids]);

                            Notification::make()
                                ->title('Print Preview Ready')
                                ->body("Opening {$eligible->count()} receiving cop(ies) in a new tab.")
                                ->success()
                                ->actions([
                                    \Filament\Actions\Action::make('open')
                                        ->label('Open Print Page')
                                        ->url($url)
                                        ->openUrlInNewTab()
                                        ->button(),
                                ])
                                ->persistent()
                                ->send();
                        }),
                    BulkAction::make('bulk_transmit')
                        ->label('Create Transmittal')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            \Filament\Forms\Components\TextInput::make('transmitted_to')
                                ->label('Transmitted To')
                                ->placeholder('e.g. Site Manager / Supervisor name')
                                ->required(),
                            \Filament\Forms\Components\TextInput::make('transmitted_by')
                                ->label('Transmitted By')
                                ->default(fn () => Auth::user()?->name ?? '')
                                ->required(),
                            \Filament\Forms\Components\TextInput::make('purpose')
                                ->label('Purpose')
                                ->placeholder('e.g. New hire uniform issuance'),
                            \Filament\Forms\Components\TextInput::make('instructions')
                                ->label('Instructions')
                                ->placeholder('e.g. Please sign and return copy'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $eligible = $records->filter(
                                fn ($r) => in_array($r->uniform_issuance_status, ['partial', 'issued'])
                            );

                            if ($eligible->isEmpty()) {
                                Notification::make()
                                    ->title('No eligible records')
                                    ->body('Bulk transmittal only works for partial or issued issuances.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // ── Merge ALL items from ALL issuances into one summary ──
                            $summaryMap  = [];
                            $issuanceIds = [];

                            foreach ($eligible as $issuance) {
                                $issuance->loadMissing(
                                    'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                    'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant'
                                );

                                foreach ($issuance->uniformIssuanceRecipient as $recipient) {
                                    foreach ($recipient->uniformIssuanceItem as $item) {
                                        $qty = (int) ($item->released_quantity ?: $item->quantity);
                                        if ($qty <= 0) continue;

                                        $itemName = $item->uniformItem?->uniform_item_name ?? '—';
                                        $size     = $item->uniformItemVariant?->uniform_item_size ?? '—';
                                        $key      = $itemName . '||' . $size;

                                        if (!isset($summaryMap[$key])) {
                                            $summaryMap[$key] = ['item_name' => $itemName, 'size' => $size, 'qty' => 0];
                                        }
                                        $summaryMap[$key]['qty'] += $qty;
                                    }
                                }

                                $issuanceIds[] = $issuance->id;
                            }

                            if (empty($summaryMap)) {
                                Notification::make()
                                    ->title('Nothing to transmit')
                                    ->body('No items found in the selected issuances.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            // ── Create ONE transmittal ──
                            $transmittal = \App\Models\Transmittals::create([
                                'uniform_issuance_id' => $eligible->first()->id,
                                'transmittal_number'  => \App\Models\Transmittals::generateNumber(),
                                'transmitted_by'      => $data['transmitted_by'],
                                'transmitted_to'      => $data['transmitted_to'],
                                'purpose'             => $data['purpose'] ?? '',
                                'instructions'        => $data['instructions'] ?? '',
                                'items_summary'       => array_values($summaryMap),
                                'transmitted_at'      => now()->toDateString(),
                                'status'              => 'pending',
                            ]);

                            // ── Attach all issuances to this transmittal via pivot ──
                            $transmittal->issuances()->attach($issuanceIds);

                            // ── Tag all eligible issuances as transmitted ──
                            \App\Models\UniformIssuances::whereIn('id', $issuanceIds)
                                ->update(['is_for_transmit' => true]);

                            $url = route('uniform-issuances.transmittal', [
                                'issuance'    => $eligible->first()->id,
                                'transmittal' => $transmittal->id,
                            ]);

                            Notification::make()
                                ->title('Transmittal Created')
                                ->body("{$transmittal->transmittal_number} — {$eligible->count()} issuance(s) bundled into 1 transmittal.")
                                ->success()
                                ->actions([
                                    \Filament\Actions\Action::make('open')
                                        ->label('Open Transmittal')
                                        ->url($url)
                                        ->openUrlInNewTab()
                                        ->button(),
                                ])
                                ->persistent()
                                ->send();
                        }),

                    // ─── BULK: FOR DELIVERY RECEIPT ────────────────────────
                    BulkAction::make('bulk_for_delivery_receipt')
                        ->label('For Delivery Receipt')
                        ->icon('heroicon-o-document-check')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            \Filament\Forms\Components\TextInput::make('endorse_by')
                                ->label('Endorsed By')
                                ->default(fn () => Auth::user()?->name ?? '')
                                ->required(),
                            \Filament\Forms\Components\DatePicker::make('endorse_date')
                                ->label('Endorse Date')
                                ->default(now()->toDateString())
                                ->required(),
                            \Filament\Forms\Components\TextInput::make('remarks')
                                ->label('Remarks')
                                ->placeholder('Optional notes or instructions'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $drEligibleTypes = ['new hire', 'additional', 'annual'];
 
                            $results   = ['created' => 0, 'skipped' => [], 'errors' => []];
 
                            foreach ($records as $record) {
                                // ── Must be partial or issued ──
                                if (!in_array($record->uniform_issuance_status, ['partial', 'issued'])) {
                                    $results['skipped'][] = "#{$record->id}: Not partial/issued (status: {$record->uniform_issuance_status})";
                                    continue;
                                }
 
                                // ── Must be DR-eligible type ──
                                $typeName = strtolower($record->uniformIssuanceType?->uniform_issuance_type_name ?? '');
                                $isDrType = false;
                                foreach ($drEligibleTypes as $t) {
                                    if (str_contains($typeName, $t)) { $isDrType = true; break; }
                                }
                                if (!$isDrType) {
                                    $results['skipped'][] = "#{$record->id}: Type '{$typeName}' not eligible for DR (must be new hire, additional, or annual)";
                                    continue;
                                }
 
                                // ── Already has a DR ──
                                if (\App\Models\ForDeliveryReceipt::where('uniform_issuance_id', $record->id)->exists()) {
                                    $results['skipped'][] = "#{$record->id}: DR already exists";
                                    continue;
                                }
 
                                // ── Must have at least one non-reliever ──
                                $record->loadMissing(
                                    'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                    'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                    'uniformIssuanceRecipient.position'
                                );
 
                                $hasNonReliever = $record->uniformIssuanceRecipient->contains(function ($recipient) {
                                    return strtolower($recipient->position?->position_name ?? '') !== 'reliever'
                                        && strtolower($recipient->employee_status ?? '') !== 'reliever';
                                });
 
                                if (!$hasNonReliever) {
                                    $results['skipped'][] = "#{$record->id}: All recipients are relievers";
                                    continue;
                                }
 
                                // ── Build item summary ──
                                $grouped = [];
                                foreach ($record->uniformIssuanceRecipient as $recipient) {
                                    $isReliever = strtolower($recipient->position?->position_name ?? '') === 'reliever'
                                        || strtolower($recipient->employee_status ?? '') === 'reliever';
                                    if ($isReliever) continue;
 
                                    $employeeName  = $recipient->employee_name ?? '—';
                                    $employeeItems = [];
 
                                    foreach ($recipient->uniformIssuanceItem as $item) {
                                        $qty = (int) $item->released_quantity;
                                        if ($qty <= 0) continue;
                                        $key = $item->uniform_item_id . '_' . $item->uniform_item_variant_id;
                                        if (!isset($employeeItems[$key])) {
                                            $employeeItems[$key] = [
                                                'employee'  => $employeeName,
                                                'item_name' => $item->uniformItem?->uniform_item_name ?? '—',
                                                'size'      => $item->uniformItemVariant?->uniform_item_size ?? '—',
                                                'qty'       => 0,
                                            ];
                                        }
                                        $employeeItems[$key]['qty'] += $qty;
                                    }
 
                                    if (!empty($employeeItems)) {
                                        foreach ($employeeItems as $row) {
                                            $grouped[] = $row;
                                        }
                                    }
                                }
 
                                if (empty($grouped)) {
                                    $results['skipped'][] = "#{$record->id}: No released items found";
                                    continue;
                                }
 
                                try {
                                    \App\Models\ForDeliveryReceipt::create([
                                        'uniform_issuance_id' => $record->id,
                                        'endorse_by'          => $data['endorse_by'],
                                        'endorse_date'        => $data['endorse_date'] ?? now()->toDateString(),
                                        'item_summary'        => $grouped,
                                        'status'              => 'pending',
                                        'done_date'           => null,
                                        'cancel_date'         => null,
                                        'remarks'             => $data['remarks'] ?? null,
                                    ]);
                                    $results['created']++;
                                } catch (\Throwable $e) {
                                    $results['errors'][] = "#{$record->id}: " . $e->getMessage();
                                }
                            }
 
                            // ── Result notification ──
                            $body = "{$results['created']} DR(s) created.";
                            if (!empty($results['skipped'])) {
                                $body .= "\n\nSkipped (" . count($results['skipped']) . "):\n" . implode("\n", array_slice($results['skipped'], 0, 5));
                                if (count($results['skipped']) > 5) $body .= "\n... and " . (count($results['skipped']) - 5) . " more.";
                            }
                            if (!empty($results['errors'])) {
                                $body .= "\n\nErrors (" . count($results['errors']) . "):\n" . implode("\n", array_slice($results['errors'], 0, 3));
                            }
 
                            $hasIssues = !empty($results['skipped']) || !empty($results['errors']);
                            Notification::make()
                                ->title($results['created'] > 0 ? 'Delivery Receipts Created' : 'No DRs Created')
                                ->body($body)
                                ->{$hasIssues ? ($results['created'] > 0 ? 'warning' : 'danger') : 'success'}()
                                ->send();
                        }),

                    // ─── BULK: BILLING ─────────────────────────────────────
                    BulkAction::make('bulk_billing')
                        ->label('Billing')
                        ->icon('heroicon-o-banknotes')
                        ->color('warning')
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Billing')
                        ->modalDescription('This will create billing records for all eligible selected issuances. Each issuance must have a DR (for client types) or be salary deduct type, must not have existing billing, and must have non-reliever recipients with released items.')
                        ->modalSubmitActionLabel('Create Billings')
                        ->action(function (Collection $records) {
                            $billableTypes  = ['new hire', 'additional', 'annual', 'salary deduct'];
                            $results        = ['created' => 0, 'skipped' => [], 'errors' => []];
 
                            foreach ($records as $record) {
                                // ── Must be partial or issued ──
                                if (!in_array($record->uniform_issuance_status, ['partial', 'issued'])) {
                                    $results['skipped'][] = "#{$record->id}: Not partial/issued";
                                    continue;
                                }
 
                                // ── Must be billable type ──
                                $typeName       = strtolower($record->uniformIssuanceType?->uniform_issuance_type_name ?? '');
                                $isBillableType = false;
                                foreach ($billableTypes as $t) {
                                    if (str_contains($typeName, $t)) { $isBillableType = true; break; }
                                }
                                if (!$isBillableType) {
                                    $results['skipped'][] = "#{$record->id}: Type '{$typeName}' not billable";
                                    continue;
                                }
 
                                $isSalaryDeduct  = str_contains($typeName, 'salary deduct');
                                $isClientBilling = !$isSalaryDeduct;
 
                                // ── Already has billing ──
                                if (\App\Models\UniformIssuanceBilling::where('uniform_issuance_id', $record->id)->exists()) {
                                    $results['skipped'][] = "#{$record->id}: Billing already exists";
                                    continue;
                                }
 
                                $record->loadMissing(
                                    'uniformIssuanceRecipient.uniformIssuanceItem.uniformItem',
                                    'uniformIssuanceRecipient.uniformIssuanceItem.uniformItemVariant',
                                    'uniformIssuanceRecipient.position',
                                    'site',
                                    'uniformIssuanceType'
                                );
 
                                // ── Client billing requires a DR ──
                                if ($isClientBilling && !\App\Models\ForDeliveryReceipt::where('uniform_issuance_id', $record->id)->exists()) {
                                    $results['skipped'][] = "#{$record->id}: Client billing requires a Delivery Receipt first";
                                    continue;
                                }
 
                                // ── Must have at least one non-reliever ──
                                $recipients = $record->uniformIssuanceRecipient;
                                $allRelievers = $recipients->every(function ($recipient) {
                                    return strtolower($recipient->position?->position_name ?? '') === 'reliever'
                                        || strtolower($recipient->employee_status ?? '') === 'reliever';
                                });
                                if ($allRelievers) {
                                    $results['skipped'][] = "#{$record->id}: All recipients are relievers";
                                    continue;
                                }
 
                                // ── Build billing items ──
                                $grouped       = [];
                                $recipientMeta = [];
 
                                foreach ($record->uniformIssuanceRecipient as $recipient) {
                                    $isReliever = strtolower($recipient->position?->position_name ?? '') === 'reliever'
                                        || strtolower($recipient->employee_status ?? '') === 'reliever';
                                    if ($isClientBilling && $isReliever) continue;
 
                                    $employeeName  = $recipient->employee_name ?? '—';
                                    $employeeItems = [];
 
                                    foreach ($recipient->uniformIssuanceItem as $item) {
                                        $qty = (int) $item->released_quantity;
                                        if ($qty <= 0) continue;
                                        $key = $item->uniform_item_id . '_' . $item->uniform_item_variant_id;
                                        if (!isset($employeeItems[$key])) {
                                            $employeeItems[$key] = [
                                                'employee'   => $employeeName,
                                                'item_name'  => $item->uniformItem?->uniform_item_name ?? '—',
                                                'size'       => $item->uniformItemVariant?->uniform_item_size ?? '—',
                                                'quantity'   => 0,
                                                'unit_price' => (float) ($item->uniformItem?->uniform_item_price ?? 0),
                                            ];
                                        }
                                        $employeeItems[$key]['quantity'] += $qty;
                                    }
 
                                    if (!empty($employeeItems)) {
                                        $grouped[$employeeName]       = array_values($employeeItems);
                                        $recipientMeta[$employeeName] = ['employee_status' => strtolower($recipient->employee_status ?? '')];
                                    }
                                }
 
                                if (empty($grouped)) {
                                    $results['skipped'][] = "#{$record->id}: No released items found";
                                    continue;
                                }
 
                                $billingItemsFlat = [];
                                foreach ($grouped as $empItems) {
                                    foreach ($empItems as $row) {
                                        $billingItemsFlat[] = $row;
                                    }
                                }
 
                                $computeTotal = fn (array $items) => array_sum(
                                    array_map(fn ($i) => (float) ($i['unit_price'] ?? 0) * (int) ($i['quantity'] ?? 0), $items)
                                );
 
                                try {
                                    if ($isClientBilling) {
                                        $total = $computeTotal($billingItemsFlat);
                                        \App\Models\UniformIssuanceBilling::create([
                                            'uniform_issuance_id'   => $record->id,
                                            'billed_to'             => $record->site?->client?->client_name ?? $record->site?->site_name ?? '—',
                                            'billing_type'          => 'client',
                                            'billing_items'         => $billingItemsFlat,
                                            'employee_attachments'  => null,
                                            'total_price'           => $total,
                                            'status'                => 'pending',
                                            'billed_at'             => null,
                                            'created_by'            => Auth::id(),
                                            'signed_receiving_copy' => null,
                                        ]);
                                        $results['created']++;
 
                                    } elseif ($isSalaryDeduct) {
                                        foreach ($grouped as $employeeName => $items) {
                                            $total = $computeTotal($items);
                                            \App\Models\UniformIssuanceBilling::create([
                                                'uniform_issuance_id'   => $record->id,
                                                'billed_to'             => $employeeName,
                                                'billing_type'          => 'salary_deduct',
                                                'billing_items'         => $items,
                                                'employee_attachments'  => null,
                                                'total_price'           => $total,
                                                'status'                => 'pending',
                                                'billed_at'             => null,
                                                'created_by'            => Auth::id(),
                                                'signed_receiving_copy' => null,
                                            ]);
                                        }
                                        $results['created']++;
                                    }
                                } catch (\Throwable $e) {
                                    $results['errors'][] = "#{$record->id}: " . $e->getMessage();
                                }
                            }
 
                            // ── Result notification ──
                            $body = "{$results['created']} issuance(s) billed.";
                            if (!empty($results['skipped'])) {
                                $body .= "\n\nSkipped (" . count($results['skipped']) . "):\n" . implode("\n", array_slice($results['skipped'], 0, 6));
                                if (count($results['skipped']) > 6) $body .= "\n... and " . (count($results['skipped']) - 6) . " more.";
                            }
                            if (!empty($results['errors'])) {
                                $body .= "\n\nErrors (" . count($results['errors']) . "):\n" . implode("\n", array_slice($results['errors'], 0, 3));
                            }
 
                            $hasIssues = !empty($results['skipped']) || !empty($results['errors']);
                            Notification::make()
                                ->title($results['created'] > 0 ? 'Billings Created' : 'No Billings Created')
                                ->body($body)
                                ->{$hasIssues ? ($results['created'] > 0 ? 'warning' : 'danger') : 'success'}()
                                ->send();
                        }),
                
                ]),
            ]);
    }
}