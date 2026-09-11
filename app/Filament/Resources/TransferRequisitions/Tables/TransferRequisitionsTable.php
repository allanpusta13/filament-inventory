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
use Illuminate\Database\Eloquent\Builder;

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

                \Filament\Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),

                \Filament\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->status === 'draft')
                    ->modalWidth(\Filament\Support\Enums\Width::Large),

                \Filament\Actions\Action::make('submitRequest')
                    ->label('SUBMIT REQUEST')
                    ->icon(\Filament\Support\Icons\Heroicon::PaperAirplane)
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

                \Filament\Actions\Action::make('reviewNegotiate')
                    ->label('REVIEW / NEGOTIATE')
                    ->icon(\Filament\Support\Icons\Heroicon::ChatBubbleLeftRight)
                    ->color('warning')
                    ->visible(fn ($record) => in_array($record->status, ['requested', 'under_review_fulfiller', 'under_review_requestor']))
                    ->url(fn ($record) => $record->getUrl('edit')),

                \Filament\Actions\Action::make('acceptRevision')
                    ->label('ACCEPT REVISION')
                    ->icon(\Filament\Support\Icons\Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, ['under_review_fulfiller', 'under_review_requestor'])),

                \Filament\Actions\Action::make('rejectRevision')
                    ->label('REJECT REVISION')
                    ->icon(\Filament\Support\Icons\Heroicon::XCircle)
                    ->color('danger')
                    ->visible(fn ($record) => in_array($record->status, ['under_review_fulfiller', 'under_review_requestor'])),

                \Filament\Actions\Action::make('confirm')
                    ->label('CONFIRM')
                    ->icon(\Filament\Support\Icons\Heroicon::CheckBadge)
                    ->color('primary')
                    ->authorize('confirm')
                    ->visible(fn ($record) => in_array($record->status, ['requested', 'under_review_fulfiller', 'under_review_requestor'])),

                \Filament\Actions\Action::make('dispatch')
                    ->label('DISPATCH')
                    ->icon(\Filament\Support\Icons\Heroicon::Truck)
                    ->color('primary')
                    ->authorize('dispatch')
                    ->visible(fn ($record) => $record->status === 'confirmed'),

                \Filament\Actions\Action::make('scanToReceive')
                    ->label('SCAN TO RECEIVE')
                    ->name('scanToReceive')
                    ->icon(\Filament\Support\Icons\Heroicon::QrCode)
                    ->color('success')
                    ->authorize('receive')
                    ->visible(fn ($record) => in_array($record->status, ['dispatched', 'partially_received'])),

                // Cancellation only pre-dispatch
                \Filament\Actions\Action::make('cancel')
                    ->label('CANCEL')
                    ->icon(\Filament\Support\Icons\Heroicon::XMark)
                    ->color('danger')
                    ->authorize('cancel')
                    ->visible(fn ($record) => in_array($record->status, [
                        'draft',
                        'requested',
                        'under_review_fulfiller',
                        'under_review_requestor',
                        'confirmed',
                    ])),

                \Filament\Actions\DeleteAction::make()
                    ->authorize('delete')
                    ->visible(fn ($record) => in_array($record->status, [
                        'draft',
                        'cancelled',
                    ])),

                \Filament\Actions\RestoreAction::make()
                    ->authorize('restore'),

                \Filament\Actions\ForceDeleteAction::make()
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make()
                        ->authorize('deleteAny'),

                    \Filament\Actions\RestoreBulkAction::make()
                        ->authorize('restoreAny'),

                    \Filament\Actions\ForceDeleteBulkAction::make()
                        ->authorize('forceDeleteAny'),
                ]),
            ]);
    }
}