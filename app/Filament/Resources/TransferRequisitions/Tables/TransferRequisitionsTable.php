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
                    ->preload(),

                SelectFilter::make('to_warehouse_id')
                    ->label('RECEIVING WAREHOUSE')
                    ->relationship('toWarehouse', 'name')
                    ->searchable()
                    ->preload(),

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
                    ->visible(fn ($record) => in_array($record->status, ['dispatched', 'partially_received'])),

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
