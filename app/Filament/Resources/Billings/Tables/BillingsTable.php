<?php

namespace App\Filament\Resources\Billings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

class BillingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->searchable(),
                TextColumn::make('billing_title')
                    ->searchable(),
                TextColumn::make('client.client_name')
                    ->searchable(),
                TextColumn::make('client.assignedUsers.name')
                    ->label('Assigned To')
                    ->badge()
                    ->color('info')
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('billing_start_period')
                    ->date()
                    ->sortable(),
                TextColumn::make('billing_end_period')
                    ->date()
                    ->sortable(),
                TextColumn::make('billing_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->money('PHP'),
                TextColumn::make('remaining_balance')
                    ->money('PHP'),
                TextColumn::make('status')
                    ->colors([
                        'danger'        => 'overdue',
                        'warning'       => 'pending',
                        'warning'       => 'partially_paid',
                        'success'       => 'paid',
                    ])
                    ->badge(),
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
                EditAction::make(),
                Action::make('view_includes')
                    ->label('Includes')
                    ->icon('heroicon-o-rectangle-stack')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Included Items — {$record->invoice_number}")
                    ->modalDescription(fn ($record) => "Client: {$record->client->client_name} · Total: ₱" . number_format($record->total_amount, 2))
                    ->modalWidth('4xl')
                    ->modalContent(function ($record) {
                        $includes = $record->includes()->with('includeable')->get();

                        if ($includes->isEmpty()) {
                            return new \Illuminate\Support\HtmlString(
                                '<p class="text-center text-gray-400 py-6">No linked items found for this billing.</p>'
                            );
                        }

                        $rows = '';
                        foreach ($includes as $index => $include) {
                            $bg        = $index % 2 === 0 ? '#ffffff' : '#f8fafc';
                            $label     = e($include->label ?? '—');
                            $amount    = '₱' . number_format($include->amount, 2);
                            $type      = $include->includeable_type
                                ? class_basename($include->includeable_type)
                                : 'Manual';

                            $typeLabel = match ($type) {
                                'SmeBilling'             => 'SME',
                                'UniformIssuanceBilling' => 'Uniform',
                                'Manual'                 => 'Manual',
                                default                  => $type,
                            };

                            $typeBg = match ($type) {
                                'SmeBilling'             => '#1d4ed8',
                                'UniformIssuanceBilling' => '#7c3aed',
                                'Manual'                 => '#0d9488',
                                default                  => '#6b7280',
                            };

                            // For manual entries, includeable is null so handle gracefully
                            $billedTo  = e($include->includeable?->billed_to ?? $include->label ?? '—');
                            $billedAt  = $include->includeable?->billed_at?->format('M d, Y') ?? '—';
                            $includedAt = $include->included_at
                                ? \Carbon\Carbon::parse($include->included_at)->timezone('Asia/Manila')->format('M d, Y h:i A')
                                : '—';

                            $rows .= "
                                <tr style='background:{$bg};'>
                                    <td style='padding:10px 14px;font-size:13px;color:#111827;border-bottom:1px solid #e5e7eb;'>
                                        <span style='background:{$typeBg};color:#fff;font-size:10px;font-weight:700;
                                            padding:2px 10px;border-radius:999px;margin-right:6px;'>{$typeLabel}</span>
                                        {$label}
                                    </td>
                                    <td style='padding:10px 14px;font-size:13px;color:#374151;border-bottom:1px solid #e5e7eb;'>{$billedTo}</td>
                                    <td style='padding:10px 14px;font-size:13px;color:#374151;border-bottom:1px solid #e5e7eb;text-align:center;'>{$billedAt}</td>
                                    <td style='padding:10px 14px;font-size:12px;color:#6b7280;border-bottom:1px solid #e5e7eb;text-align:center;'>{$includedAt}</td>
                                    <td style='padding:10px 14px;font-size:13px;font-weight:700;color:#1d4ed8;border-bottom:1px solid #e5e7eb;text-align:right;'>{$amount}</td>
                                </tr>";
                        }

                        $grandTotal = '₱' . number_format($includes->sum('amount'), 2);

                        return new \Illuminate\Support\HtmlString("
                            <div style='font-family:\"DM Sans\",system-ui,sans-serif;'>
                                <div style='border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;'>
                                    <table style='width:100%;border-collapse:collapse;'>
                                        <thead>
                                            <tr style='background:#1e3a5f;'>
                                                <th style='padding:10px 14px;text-align:left;font-size:10.5px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Source / Label</th>
                                                <th style='padding:10px 14px;text-align:left;font-size:10.5px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Billed To</th>
                                                <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:700;color:#e0f2fe;text-transform:uppercase;letter-spacing:.05em;'>Billed At</th>
                                                <th style='padding:10px 14px;text-align:center;font-size:10.5px;font-weight:700;color:#fcd34d;text-transform:uppercase;letter-spacing:.05em;'>Included At</th>
                                                <th style='padding:10px 14px;text-align:right;font-size:10.5px;font-weight:700;color:#fcd34d;text-transform:uppercase;letter-spacing:.05em;'>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>{$rows}</tbody>
                                        <tfoot>
                                            <tr style='background:#eff6ff;border-top:2px solid #93c5fd;'>
                                                <td colspan='4' style='padding:11px 14px;font-size:12px;font-weight:600;color:#374151;text-align:right;'>
                                                    Total Linked Amount
                                                </td>
                                                <td style='padding:11px 14px;font-size:15px;font-weight:800;color:#1d4ed8;text-align:right;'>
                                                    {$grandTotal}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        ");
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->visible(fn ($record) => $record->includes()->exists()),

                Action::make('addItem')
                    ->label('Add Item')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->modalHeading(fn ($record) => "Add Item — {$record->invoice_number}")
                    ->modalWidth('lg')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('label')
                            ->label('Label / Description')
                            ->placeholder('e.g. Uniform - Juan Dela Cruz')
                            ->required()
                            ->columnSpanFull(),

                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('₱')
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required()
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Select::make('item_type')
                            ->label('Type')
                            ->options([
                                'manual'    => 'Manual Entry',
                                'uniform'   => 'Uniform',
                                'sme'       => 'SME',
                                'other'     => 'Other',
                            ])
                            ->default('manual')
                            ->required()
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Optional notes...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->modalSubmitActionLabel('Add to Billing')
                    ->action(function ($record, array $data): void {
                        // ── Add to billing_includes as a manual entry ──
                        \App\Models\BillingInclude::create([
                            'billing_id'       => $record->id,
                            'includeable_type' => null,
                            'includeable_id'   => null,
                            'amount'           => (float) $data['amount'],
                            'label'            => $data['label'],
                            'included_at'      => now(),
                        ]);

                        // ── Update billing total_amount ──
                        \Illuminate\Support\Facades\DB::table('billings')
                            ->where('id', $record->id)
                            ->update([
                                'total_amount' => \Illuminate\Support\Facades\DB::raw(
                                    'COALESCE(total_amount, 0) + ' . (float) $data['amount']
                                ),
                            ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Item Added')
                            ->body('₱' . number_format((float) $data['amount'], 2) . ' added to billing "' . $record->billing_title . '".')
                            ->success()
                            ->send();
                    }),

                Action::make('payment')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        TextInput::make('collect_by')
                            ->required(),
                        TextInput::make('amount_paid')
                            ->numeric()
                            ->minValue(0.01)
                            ->rules(['gt:0'])
                            ->step(0.01)
                            ->prefix('₱')
                            ->required()
                            ->default(fn ($record) => $record->remaining_balance)
                            ->maxValue(fn ($record) => $record->remaining_balance),
                        DatePicker::make('payment_date')
                            ->default(now())
                            ->required(),
                        Select::make('payment_method')
                            ->options([
                                'cash'          => 'Cash',
                                'gcash'         => 'GCash',
                                'bank_transfer' => 'Bank Transfer',
                                'check'         => 'Check'
                            ])
                            ->required(),
                        TextInput::make('reference_number')
                    ])
                    ->action(function ($record, array $data) {
                        
                        $billing = $record;

                        $collection = $billing->collection()->create([
                            'collect_by'        => $data['collect_by'],
                            'amount_paid'       => $data['amount_paid'],
                            'payment_date'      => $data['payment_date'],
                            'payment_method'    => $data['payment_method'],
                            'reference_number'  => $data['reference_number'],
                        ]);

                        $totalPaid = $billing->collection()->sum('amount_paid');
                        $isOverdue = $billing->due_date < now()->toDateString();

                        $billing->update([
                            'status' => match(true) {
                                $totalPaid >= $billing->total_amount                        => 'paid',
                                $totalPaid > 0 && $totalPaid < $billing->total_amount      => 'partially_paid',
                                $isOverdue                                                  => 'overdue',
                                default                                                     => 'pending'
                            }
                        ]);
                    })
                    ->successNotificationTitle('Payment recorded successfully'),
                Action::make('collection_logs')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->modalHeading(fn ($record) => "Collection Logs - {$record->invoice_number}")
                    ->modalDescription(fn ($record) => "Client: {$record->client->client_name}")
                    ->modalWIdth('5xl')
                    ->modalContent(fn ($record) => view(
                        'filament.billings.collection-logs-modal',
                        ['record' => $record->load('collection')]
                    ))
                    ->modalSubmitAction(False)
                    ->modalCancelActionLabel('Close'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
