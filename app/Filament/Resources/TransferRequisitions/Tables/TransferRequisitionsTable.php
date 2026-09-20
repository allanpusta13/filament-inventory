<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Tables;

use App\Enums\TransferRequisitionStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TransferRequisitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')
                    ->label('REFERENCE CODE')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('primary'),

                TextColumn::make('fromWarehouse.name')
                    ->label('ORIGIN SITE')
                    ->sortable(),

                TextColumn::make('toWarehouse.name')
                    ->label('RECEIVING SITE')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('OPERATIONAL STATUS')
                    ->badge(),

                TextColumn::make('requested_at')
                    ->label('SUBMITTED ON')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('COMPLETED ON')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requested_at', 'desc')
            ->recordClasses(fn ($record) => match ($record->status) {
                'under_review_fulfiller', 'under_review_requestor' => 'hover:bg-amber-50/40 dark:hover:bg-amber-950/20 transition-colors',
                default => 'hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors',
            })
            ->filters([
                SelectFilter::make('status')
                    ->options(TransferRequisitionStatus::class)
                    ->label('OPERATIONAL STATUS'),

                SelectFilter::make('from_warehouse_id')
                    ->label('ORIGIN WAREHOUSE')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false || auth()->user()?->isAuditor() ?? false),

                SelectFilter::make('to_warehouse_id')
                    ->label('RECEIVING WAREHOUSE')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false || auth()->user()?->isAuditor() ?? false),

                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->modalWidth(\Filament\Support\Enums\Width::Large),

                Action::make('submitRequest')
                    ->label('SUBMIT REQUEST')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('primary')
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'requested',
                            'requested_at' => now(),
                            'requested_by' => auth()->id(),
                        ]);
                    })
                    ->requiresConfirmation(),

                Action::make('reviewNegotiate')
                    ->label('REVIEW / NEGOTIATE')
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->status, ['requested', 'under_review_fulfiller', 'under_review_requestor']))
                    ->url(fn ($record) => $record->getUrl('edit')),

                Action::make('acceptRevision')
                    ->label('ACCEPT REVISION')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['under_review_fulfiller', 'under_review_requestor'])),

                Action::make('rejectRevision')
                    ->label('REJECT REVISION')
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['under_review_fulfiller', 'under_review_requestor'])),

                Action::make('confirm')
                    ->label('CONFIRM')
                    ->icon(Heroicon::CheckBadge)
                    ->color('primary')
                    ->authorize('confirm')
                    ->visible(fn ($record) => in_array($record->status, ['requested', 'under_review_fulfiller', 'under_review_requestor']))
                    ->action(function ($record) {
                        // Only materialize requested for items that were never negotiated
                        // (items with negotiated revisions already have approved_* fields set)
                        app(\App\Services\NegotiationService::class)
                            ->materializeRequestedAsApproved($record);
                        $record->update([
                            'status' => 'confirmed',
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);
                    })
                    ->requiresConfirmation(),

                Action::make('dispatch')
                    ->label('DISPATCH')
                    ->icon(Heroicon::Truck)
                    ->color('primary')
                    ->authorize('dispatch')
                    ->visible(fn ($record) => $record->status === 'confirmed'),

                Action::make('scanToReceive')
                    ->label('SCAN TO RECEIVE')
                    ->name('scanToReceive')
                    ->icon(Heroicon::QrCode)
                    ->color('success')
                    ->authorize('receive')
                    ->visible(fn ($record) => in_array($record->status, ['dispatched', 'partially_received']))
                    ->url(fn ($record) => route('stn.scan', ['transferRequisition' => $record->id])),

                Action::make('recordLoss')
                    ->label('RECORD LOSS')
                    ->name('recordLoss')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->color('danger')
                    ->authorize('recordLoss')
                    ->visible(fn ($record) => in_array($record->status->value, [TransferRequisitionStatus::Dispatched->value, TransferRequisitionStatus::PartiallyReceived->value]))
                    ->modalWidth(\Filament\Support\Enums\Width::Large)
                    ->schema([
                        \Filament\Forms\Components\Select::make('product_variant_id')
                            ->label('Product Variant')
                            ->options(fn ($record) => $record->items->pluck('productVariant.name', 'product_variant_id')->toArray())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $get) => $set('total_financial_loss', null)),
                        \Filament\Forms\Components\Select::make('loss_category')
                            ->label('Loss Category')
                            ->options([
                                'shortfall' => 'Shortfall',
                                'damage' => 'Damage',
                                'spoilage' => 'Spoilage',
                                'theft' => 'Theft',
                                'other' => 'Other',
                            ])
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('lost_base_qty')
                            ->label('Lost Quantity (Base)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $get) => $set('total_financial_loss', null)),
                        \Filament\Forms\Components\TextInput::make('damaged_base_qty')
                            ->label('Damaged Quantity (Base)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $get) => $set('total_financial_loss', null)),
                        \Filament\Forms\Components\TextInput::make('total_financial_loss')
                            ->label('Total Financial Loss (Auto-calculated)')
                            ->numeric()
                            ->minValue(0)
                            ->disabled()
                            ->dehydrated(false),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, $record) {
                        $variant = \App\Models\ProductVariant::find($data['product_variant_id']);
                        $unitCost = \App\Models\LossLedger::snapshotUnitCostFrom($variant);
                        $totalQty = (int) $data['lost_base_qty'] + (int) $data['damaged_base_qty'];
                        $totalFinancialLoss = \App\Models\LossLedger::calculateTotalFinancialLoss($unitCost, $totalQty);
                        $record->lossLedgers()->create([
                            'transfer_requisition_item_id' => $record->items->where('product_variant_id', $data['product_variant_id'])->first()?->id,
                            'product_variant_id' => $data['product_variant_id'],
                            'warehouse_id' => $record->to_warehouse_id,
                            'loss_category' => $data['loss_category'],
                            'lost_base_qty' => $data['lost_base_qty'],
                            'damaged_base_qty' => $data['damaged_base_qty'],
                            'unit_cost_price' => $unitCost,
                            'total_financial_loss' => $totalFinancialLoss,
                            'notes' => $data['notes'],
                            'recorded_by' => auth()->id(),
                            'recorded_at' => now(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Loss recorded')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                // Cancellation only pre-dispatch
                Action::make('cancel')
                    ->label('CANCEL')
                    ->icon(Heroicon::XMark)
                    ->color('danger')
                    ->authorize('cancel')
                    ->visible(fn ($record) => in_array($record->status, [
                        'draft',
                        'requested',
                        'under_review_fulfiller',
                        'under_review_requestor',
                        'confirmed',
                    ])),

                DeleteAction::make()
                    ->authorize('delete')
                    ->visible(fn ($record) => in_array($record->status, [
                        'draft',
                        'cancelled',
                    ])),

                RestoreAction::make()
                    ->authorize('restore'),

                ForceDeleteAction::make()
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorize('deleteAny'),

                    RestoreBulkAction::make()
                        ->authorize('restoreAny'),

                    ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                ]),
            ]);
    }
}
