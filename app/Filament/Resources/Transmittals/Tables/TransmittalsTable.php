<?php

namespace App\Filament\Resources\Transmittals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use App\Models\TransmittalLog;

class TransmittalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transmittal_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('transmitted_by')
                    ->searchable(),
                TextColumn::make('transmitted_to')
                    ->searchable(),
                TextColumn::make('purpose')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('instructions')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('transmitted_at')
                    ->label('Transmitted')
                    ->date()
                    ->sortable(),

                // ─── SMART DATE — shows date based on current status ───────
                TextColumn::make('status_date')
                    ->label('Status Date')
                    ->state(function ($record): string {
                        return match ($record->status) {
                            'received_from_office' => $record->date_received_from_office
                                ? $record->date_received_from_office->format('M d, Y')
                                : '—',
                            'received_from_site'   => $record->date_received_from_site
                                ? $record->date_received_from_site->format('M d, Y')
                                : '—',
                            'document_returned'    => $record->date_returned
                                ? $record->date_returned->format('M d, Y')
                                : '—',
                            default => '—',
                        };
                    })
                    ->description(fn ($record): string => match ($record->status) {
                        'received_from_office' => 'Received from Office',
                        'received_from_site'   => 'Received from Site',
                        'document_returned'    => 'Document Returned',
                        default                => 'Pending',
                    })
                    ->sortable(false),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'              => 'Pending',
                        'received_from_office' => 'Recv. Office',
                        'received_from_site'   => 'Recv. Site',
                        'document_returned'    => 'Doc Returned',
                        default                => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'              => 'warning',
                        'received_from_office' => 'info',
                        'received_from_site'   => 'success',
                        'document_returned'    => 'gray',
                        default                => 'gray',
                    }),

                TextColumn::make('uniform_issuance_id')
                    ->label('Linked Issuance')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->defaultSort('created_at', 'desc')
            ->recordActions([

                // ─── VIEW ──────────────────────────────────────────────────
                Action::make('view')
                    ->label('View')
                    ->color('gray')
                    ->icon('heroicon-o-eye')
                    ->modalWidth('3xl')
                    ->modalHeading(fn ($record) => 'Transmittal — ' . $record->transmittal_number)
                    ->modalContent(function ($record) {
                        $transmittalNumber = e($record->transmittal_number);
                        $transmittedBy     = e($record->transmitted_by);
                        $transmittedTo     = e($record->transmitted_to);
                        $purpose           = e($record->purpose ?? '—');
                        $instructions      = e($record->instructions ?? '—');
                        $transmittedAt     = $record->transmitted_at
                            ? \Carbon\Carbon::parse($record->transmitted_at)->format('F d, Y')
                            : '—';

                        $statusColor = match ($record->status) {
                            'received_from_office' => '#0284c7',
                            'received_from_site'   => '#16a34a',
                            'document_returned'    => '#6b7280',
                            default                => '#d97706',
                        };
                        $statusLabel = match ($record->status) {
                            'pending'              => 'PENDING',
                            'received_from_office' => 'RECV. OFFICE',
                            'received_from_site'   => 'RECV. SITE',
                            'document_returned'    => 'DOC RETURNED',
                            default                => strtoupper($record->status),
                        };

                        // Items rows
                        $items = is_array($record->items_summary)
                            ? $record->items_summary
                            : (json_decode($record->items_summary, true) ?? []);

                        $grouped = [];
                        foreach ($items as $row) {
                            // Old saved transmittals (pre-fix) won't have 'employee' — fall back to a bucket
                            // so the modal still renders instead of throwing a key error.
                            $employee = trim($row['employee'] ?? '') ?: 'Unassigned';

                            $grouped[$employee][] = [
                                'item_name' => $row['item_name'] ?? $row['item'] ?? '—',
                                'size'      => $row['size'] ?? '—',
                                'qty'       => (int) ($row['qty'] ?? $row['quantity'] ?? 0),
                                'remarks'   => $row['remarks'] ?? '',
                            ];
                        }

                        $avatarStyles = [
                            ['bg' => '#E6F1FB', 'color' => '#0C447C'],
                            ['bg' => '#E1F5EE', 'color' => '#0F6E56'],
                            ['bg' => '#EEEDFE', 'color' => '#3C3489'],
                            ['bg' => '#FAEEDA', 'color' => '#633806'],
                            ['bg' => '#FAECE7', 'color' => '#712B13'],
                        ];

                        $recipientBlocks = '';
                        $totalItems      = 0;
                        $empIndex        = 0;

                        foreach ($grouped as $employeeName => $empItems) {
                            $empIndex++;
                            $av = $avatarStyles[($empIndex - 1) % count($avatarStyles)];

                            $words    = explode(' ', trim($employeeName));
                            $initials = strtoupper(
                                (isset($words[0]) ? substr($words[0], 0, 1) : '') .
                                (isset($words[1]) ? substr($words[1], 0, 1) : '')
                            );

                            $empTotal  = 0;
                            $tableRows = '';
                            foreach ($empItems as $i => $item) {
                                $itemName = e($item['item_name']);
                                $size     = e($item['size']);
                                $qty      = (int) $item['qty'];
                                $remarks  = e($item['remarks']);
                                $empTotal += $qty;
                                $bg = $i % 2 === 0 ? '#ffffff' : '#f8fafc';

                                $tableRows .= "
                                    <tr style='background:{$bg};'>
                                        <td style='padding:8px 14px;border-bottom:1px solid #f1f5f9;font-size:12.5px;color:#111827;font-weight:500;'>{$itemName}</td>
                                        <td style='padding:8px 14px;border-bottom:1px solid #f1f5f9;font-size:12.5px;color:#374151;text-align:center;'>{$size}</td>
                                        <td style='padding:8px 14px;border-bottom:1px solid #f1f5f9;font-size:12.5px;font-weight:700;text-align:center;color:#1d4ed8;'>{$qty}</td>
                                        <td style='padding:8px 14px;border-bottom:1px solid #f1f5f9;font-size:11.5px;color:#6b7280;'>{$remarks}</td>
                                    </tr>";
                            }

                            $totalItems += $empTotal;

                            $recipientBlocks .= "
                                <div style='border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:10px;'>
                                    <div style='display:flex;align-items:center;gap:12px;padding:10px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>
                                        <div style='width:32px;height:32px;border-radius:50%;background:{$av['bg']};flex-shrink:0;
                                            display:flex;align-items:center;justify-content:center;font-size:11.5px;font-weight:700;color:{$av['color']};'>
                                            {$initials}
                                        </div>
                                        <div style='flex:1;min-width:0;'>
                                            <div style='font-size:13px;font-weight:700;color:#111827;'>" . e($employeeName) . "</div>
                                        </div>
                                        <div style='background:{$av['bg']};color:{$av['color']};font-size:12px;font-weight:700;
                                            padding:3px 12px;border-radius:999px;white-space:nowrap;flex-shrink:0;'>
                                            {$empTotal}&nbsp;pc" . ($empTotal !== 1 ? 's' : '') . "
                                        </div>
                                    </div>
                                    <table style='width:100%;border-collapse:collapse;'>
                                        <thead>
                                            <tr style='background:#f1f5f9;'>
                                                <th style='padding:6px 14px;text-align:left;font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;'>Item</th>
                                                <th style='padding:6px 14px;text-align:center;font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;width:60px;'>Size</th>
                                                <th style='padding:6px 14px;text-align:center;font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;width:50px;'>Qty</th>
                                                <th style='padding:6px 14px;text-align:left;font-size:9.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.06em;'>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$tableRows}</tbody>
                                    </table>
                                </div>";
                        }

                        $empCount = $empIndex;

                        // ─── Receipt tracking timeline ─────────────────────
                        $steps = [
                            [
                                'label'  => 'Received from Office',
                                'name'   => $record->received_from_office,
                                'date'   => $record->date_received_from_office
                                    ? \Carbon\Carbon::parse($record->date_received_from_office)->format('F d, Y')
                                    : null,
                                'color'  => '#0284c7',
                                'done'   => ! is_null($record->received_from_office),
                            ],
                            [
                                'label'  => 'Received from Site',
                                'name'   => $record->received_from_site,
                                'date'   => $record->date_received_from_site
                                    ? \Carbon\Carbon::parse($record->date_received_from_site)->format('F d, Y')
                                    : null,
                                'color'  => '#16a34a',
                                'done'   => ! is_null($record->received_from_site),
                            ],
                            [
                                'label'  => 'Document Returned',
                                'name'   => $record->returned_by,
                                'date'   => $record->date_returned
                                    ? \Carbon\Carbon::parse($record->date_returned)->format('F d, Y')
                                    : null,
                                'color'  => '#6b7280',
                                'done'   => ! is_null($record->returned_by),
                            ],
                        ];

                        $remarksVal   = e($record->remarks ?? '—');
                        $timelineRows = '';

                        foreach ($steps as $step) {
                            $dot     = $step['done']
                                ? "<div style='width:12px;height:12px;border-radius:50%;background:{$step['color']};flex-shrink:0;margin-top:3px;'></div>"
                                : "<div style='width:12px;height:12px;border-radius:50%;border:2px solid #d1d5db;flex-shrink:0;margin-top:3px;background:#fff;'></div>";
                            $nameVal = $step['done'] ? e($step['name'] ?? '—') : '<span style="color:#9ca3af;font-style:italic;">Not yet recorded</span>';
                            $dateVal = $step['done'] ? "<span style='color:#6b7280;font-size:11px;'>{$step['date']}</span>" : '';

                            $timelineRows .= "
                                <div style='display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;'>
                                    {$dot}
                                    <div style='flex:1;'>
                                        <div style='font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;'>{$step['label']}</div>
                                        <div style='font-size:13px;font-weight:600;color:#111827;margin-top:2px;'>{$nameVal}</div>
                                        {$dateVal}
                                    </div>
                                </div>";
                        }

                        $receiptTrackingHtml = "
                            <div style='margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                                <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                    Receipt Tracking
                                </div>
                                <div style='padding:4px 14px;'>
                                    {$timelineRows}
                                </div>
                                <div style='padding:10px 14px;background:#fafafa;border-top:1px solid #e2e8f0;'>
                                    <div style='font-size:10px;color:#6b7280;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;'>Remarks</div>
                                    <div style='font-size:13px;color:#374151;'>{$remarksVal}</div>
                                </div>
                            </div>";

                        // Linked issuances
                        $linkedIssuancesHtml = '';
                        if ($record->issuances && $record->issuances->count() > 0) {
                            $rows = '';
                            foreach ($record->issuances as $issuance) {
                                $siteName = e($issuance->site?->site_name ?? '—');
                                $typeName = e($issuance->uniformIssuanceType?->uniform_issuance_type_name ?? '—');
                                $status   = strtoupper($issuance->uniform_issuance_status ?? '—');
                                $statusC  = match ($issuance->uniform_issuance_status) {
                                    'issued'    => '#16a34a',
                                    'partial'   => '#d97706',
                                    'pending'   => '#2563eb',
                                    'cancelled' => '#dc2626',
                                    default     => '#6b7280',
                                };
                                $rows .= "
                                    <tr>
                                        <td style='padding:7px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#111827;'>{$siteName}</td>
                                        <td style='padding:7px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#374151;'>{$typeName}</td>
                                        <td style='padding:7px 12px;border-bottom:1px solid #e5e7eb;text-align:center;'>
                                            <span style='background:{$statusC};color:#fff;font-size:9.5px;font-weight:700;padding:2px 8px;border-radius:999px;'>{$status}</span>
                                        </td>
                                    </tr>";
                            }
                            $count = $record->issuances->count();
                            $linkedIssuancesHtml = "
                                <div style='margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                                    <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                        Linked Issuances ({$count})
                                    </div>
                                    <table style='width:100%;border-collapse:collapse;'>
                                        <thead>
                                            <tr style='background:#1e3a5f;'>
                                                <th style='padding:7px 12px;text-align:left;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Site</th>
                                                <th style='padding:7px 12px;text-align:left;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Type</th>
                                                <th style='padding:7px 12px;text-align:center;font-size:10px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                    </table>
                                </div>";
                        }

                        // Print button
                        $issuanceId = $record->uniform_issuance_id ?? $record->issuances?->first()?->id;
                        $printBtnHtml = '';
                        if ($issuanceId) {
                            $printUrl     = route('uniform-issuances.transmittal', [
                                'issuance'    => $issuanceId,
                                'transmittal' => $record->id,
                            ]);
                            $printBtnHtml = "
                                <div style='margin-top:14px;text-align:right;'>
                                    <a href='{$printUrl}' target='_blank'
                                        style='display:inline-flex;align-items:center;gap:6px;background:#1e3a5f;color:#fff;
                                        font-size:12px;font-weight:600;padding:8px 18px;border-radius:8px;text-decoration:none;letter-spacing:.02em;'>
                                        🖨 Open Printable Transmittal
                                    </a>
                                </div>";
                        }

                        $logs = $record->logs()->with('user')->get();
                        $logRows = '';

                        foreach ($logs as $log) {
                            $actionLabel = e($log->action);
                            $userName    = e($log->user?->name ?? 'System');
                            $logDate     = $log->created_at->timezone('Asia/Manila')->format('M d, Y h:i A');
                            $noteVal     = e($log->note ?? '');
                            $fromBadge   = $log->status_from
                                ? "<span style='background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;font-size:10px;padding:1px 7px;border-radius:999px;'>" . e($log->status_from) . "</span>"
                                : '';
                            $toBadge     = $log->status_to
                                ? "<span style='background:#dbeafe;color:#1d4ed8;font-size:10px;padding:1px 7px;border-radius:999px;'>" . e($log->status_to) . "</span>"
                                : '';
                            $arrow       = ($fromBadge && $toBadge) ? "<span style='color:#9ca3af;margin:0 4px;'>→</span>" : '';

                            $logRows .= "
                                <div style='display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;'>
                                    <div style='width:8px;height:8px;border-radius:50%;background:#6366f1;flex-shrink:0;margin-top:5px;'></div>
                                    <div style='flex:1;'>
                                        <div style='display:flex;align-items:center;gap:6px;flex-wrap:wrap;'>
                                            <span style='font-size:12px;font-weight:700;color:#111827;'>{$actionLabel}</span>
                                            {$fromBadge}{$arrow}{$toBadge}
                                        </div>
                                        <div style='font-size:11px;color:#6b7280;margin-top:2px;'>{$userName} · {$logDate}</div>
                                        " . ($noteVal ? "<div style='font-size:12px;color:#374151;margin-top:3px;'>{$noteVal}</div>" : '') . "
                                    </div>
                                </div>";
                        }

                        $logsHtml = "
                            <div style='margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                                <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.06em;'>
                                    Activity Log
                                </div>
                                <div style='padding:4px 14px;'>
                                    " . ($logRows ?: "<div style='padding:12px 0;font-size:13px;color:#9ca3af;font-style:italic;'>No activity recorded yet.</div>") . "
                                </div>
                            </div>";

                        return new HtmlString("
                            <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                                <div style='background:linear-gradient(135deg,#1e3a5f 0%,#1e40af 100%);
                                    border-radius:12px;padding:18px 20px;margin-bottom:16px;'>
                                    <div style='display:flex;justify-content:space-between;align-items:flex-start;'>
                                        <div>
                                            <div style='font-size:18px;font-weight:800;color:#fff;letter-spacing:-0.02em;'>{$transmittalNumber}</div>
                                            <div style='font-size:12px;color:#93c5fd;margin-top:4px;'>
                                                Transmitted on <strong style='color:#e0f2fe;'>{$transmittedAt}</strong>
                                            </div>
                                        </div>
                                        <span style='background:{$statusColor};color:#fff;font-size:11px;font-weight:700;
                                            padding:4px 14px;border-radius:999px;letter-spacing:.04em;white-space:nowrap;'>
                                            {$statusLabel}
                                        </span>
                                    </div>
                                    <div style='display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;'>
                                        <div style='background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>From</div>
                                            <div style='font-size:13px;font-weight:600;color:#fff;'>{$transmittedBy}</div>
                                        </div>
                                        <div style='background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>To</div>
                                            <div style='font-size:13px;font-weight:600;color:#fff;'>{$transmittedTo}</div>
                                        </div>
                                        <div style='background:rgba(255,255,255,.08);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>Purpose</div>
                                            <div style='font-size:12.5px;color:#e0f2fe;'>{$purpose}</div>
                                        </div>
                                        <div style='background:rgba(255,255,255,.08);border-radius:8px;padding:10px 14px;'>
                                            <div style='font-size:10px;color:#93c5fd;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;'>Instructions</div>
                                            <div style='font-size:12.5px;color:#e0f2fe;'>{$instructions}</div>
                                        </div>
                                    </div>
                                </div>

                                <div style='border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;'>
                                    <div style='background:#f1f5f9;padding:9px 14px;font-size:11px;font-weight:700;color:#475569;
                                        text-transform:uppercase;letter-spacing:.06em;display:flex;justify-content:space-between;align-items:center;'>
                                        <span>Items Summary &nbsp;·&nbsp; {$empCount} recipient" . ($empCount !== 1 ? 's' : '') . "</span>
                                        <span style='color:#1d4ed8;font-size:12.5px;font-weight:800;'>{$totalItems} total pcs</span>
                                    </div>
                                    <div style='padding:10px;'>
                                        {$recipientBlocks}
                                    </div>
                                </div>

                                {$receiptTrackingHtml}
                                {$linkedIssuancesHtml}
                                {$logsHtml}  
                                {$printBtnHtml}
                            </div>
                        ");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                // ─── PRINT ─────────────────────────────────────────────────
                Action::make('print')
                    ->label('Print')
                    ->color('success')
                    ->icon('heroicon-o-printer')
                    ->action(function ($record) {
                        $issuanceId = $record->uniform_issuance_id
                            ?? $record->issuances?->first()?->id;

                        if (! $issuanceId) {
                            Notification::make()
                                ->title('Cannot Print')
                                ->body('No linked issuance found for this transmittal.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $url = route('uniform-issuances.transmittal', [
                            'issuance'    => $issuanceId,
                            'transmittal' => $record->id,
                        ]);

                        Notification::make()
                            ->title('Transmittal Ready')
                            ->body("{$record->transmittal_number} — Click the button to open the printable copy.")
                            ->success()
                            ->actions([
                                \Filament\Actions\Action::make('open')
                                    ->label('Open & Print')
                                    ->url($url)
                                    ->openUrlInNewTab()
                                    ->button(),
                            ])
                            ->persistent()
                            ->send();
                    }),

                // ─── RECEIVE FROM OFFICE ───────────────────────────────────
                Action::make('receive_from_office')
                    ->label('Receive from Office')
                    ->color('info')
                    ->icon('heroicon-o-building-office')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->modalHeading(fn ($record) => 'Receive from Office — ' . $record->transmittal_number)
                    ->modalDescription('Fill in the office receipt details.')
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('received_from_office')
                            ->label('Received from Office')
                            ->placeholder('e.g. Norman Madrid')
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('date_received_from_office')
                            ->label('Date Received from Office')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('remarks')
                            ->label('Remarks')
                            ->placeholder('e.g. RTC - Returned Transmittal Copy'),
                    ])
                    ->fillForm(fn ($record) => [
                        'received_from_office'      => $record->received_from_office,
                        'date_received_from_office' => $record->date_received_from_office,
                        'remarks'                   => $record->remarks,
                    ])
                    // ─── RECEIVE FROM OFFICE ───────────────────────────────────
                    ->action(function ($record, array $data) {
                        $from = $record->status;

                        $record->update([
                            'received_from_office'      => $data['received_from_office'],
                            'date_received_from_office' => $data['date_received_from_office'],
                            'remarks'                   => $data['remarks'] ?? null,
                            'status'                    => 'received_from_office',
                        ]);

                        TransmittalLog::create([
                            'transmittal_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'Received from Office',
                            'status_from'    => $from,
                            'status_to'      => 'received_from_office',
                            'note'           => "Received by: {$data['received_from_office']}. " . ($data['remarks'] ?? ''),
                        ]);

                        Notification::make()
                            ->title('Received from Office')
                            ->body("{$record->transmittal_number} has been marked as received from office.")
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel('Save'),

                // ─── RECEIVE FROM SITE ─────────────────────────────────────
                Action::make('receive_from_site')
                    ->label('Receive from Site')
                    ->color('success')
                    ->icon('heroicon-o-map-pin')
                    ->visible(fn ($record) => $record->status === 'received_from_office')
                    ->modalHeading(fn ($record) => 'Receive from Site — ' . $record->transmittal_number)
                    ->modalDescription('Fill in the site receipt details.')
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('received_from_site')
                            ->label('Received from Site')
                            ->placeholder('e.g. Roque Alcaraz')
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('date_received_from_site')
                            ->label('Date Received from Site')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('remarks')
                            ->label('Remarks')
                            ->placeholder('e.g. RTC - Returned Transmittal Copy'),
                    ])
                    ->fillForm(fn ($record) => [
                        'received_from_site'      => $record->received_from_site,
                        'date_received_from_site' => $record->date_received_from_site,
                        'remarks'                 => $record->remarks,
                    ])
                    // ─── RECEIVE FROM SITE ─────────────────────────────────────
                    ->action(function ($record, array $data) {
                        $from = $record->status;

                        $record->update([
                            'received_from_site'      => $data['received_from_site'],
                            'date_received_from_site' => $data['date_received_from_site'],
                            'remarks'                 => $data['remarks'] ?? null,
                            'status'                  => 'received_from_site',
                        ]);

                        TransmittalLog::create([
                            'transmittal_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'Received from Site',
                            'status_from'    => $from,
                            'status_to'      => 'received_from_site',
                            'note'           => "Received by: {$data['received_from_site']}. " . ($data['remarks'] ?? ''),
                        ]);

                        Notification::make()
                            ->title('Received from Site')
                            ->body("{$record->transmittal_number} has been marked as received from site.")
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel('Save'),

                // ─── RETURN DOCUMENT ───────────────────────────────────────
                Action::make('return_document')
                    ->label('Return Document')
                    ->color('gray')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn ($record) => $record->status === 'received_from_site')
                    ->modalHeading(fn ($record) => 'Return Document — ' . $record->transmittal_number)
                    ->modalDescription('Confirm and record the document return details.')
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('returned_by')
                            ->label('Returned By')
                            ->placeholder('e.g. Ernando Sodsod')
                            ->required(),
                        \Filament\Forms\Components\DatePicker::make('date_returned')
                            ->label('Date Returned')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('remarks')
                            ->label('Remarks')
                            ->placeholder('e.g. RTC - Returned Transmittal Copy'),
                    ])
                    ->fillForm(fn ($record) => [
                        'returned_by'   => $record->returned_by,
                        'date_returned' => $record->date_returned,
                        'remarks'       => $record->remarks,
                    ])
                    // ─── RETURN DOCUMENT ───────────────────────────────────────
                    ->action(function ($record, array $data) {
                        $from = $record->status;

                        $record->update([
                            'returned_by'   => $data['returned_by'],
                            'date_returned' => $data['date_returned'],
                            'remarks'       => $data['remarks'] ?? null,
                            'status'        => 'document_returned',
                        ]);

                        TransmittalLog::create([
                            'transmittal_id' => $record->id,
                            'user_id'        => auth()->id(),
                            'action'         => 'Document Returned',
                            'status_from'    => $from,
                            'status_to'      => 'document_returned',
                            'note'           => "Returned by: {$data['returned_by']}. " . ($data['remarks'] ?? ''),
                        ]);

                        Notification::make()
                            ->title('Document Returned')
                            ->body("{$record->transmittal_number} has been marked as document returned.")
                            ->success()
                            ->send();
                    })
                    ->modalSubmitActionLabel('Confirm Return'),

                // ─── EDIT: only when not linked ────────────────────────────
                EditAction::make()
                    ->visible(fn ($record) => is_null($record->uniform_issuance_id))
                    ->tooltip(fn ($record) => ! is_null($record->uniform_issuance_id)
                        ? 'This transmittal is linked to an issuance and cannot be edited.'
                        : null
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}