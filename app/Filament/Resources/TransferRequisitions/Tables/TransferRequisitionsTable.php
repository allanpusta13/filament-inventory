<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Tables;

use App\Enums\TransferRequisitionStatus;
use App\Models\TransferRequisition;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
                Stack::make([
                    Split::make([
                        TextColumn::make('reference_code')
                            ->label(__('resources.transfer_requisitions.table.reference'))
                            ->fontFamily('mono')
                            ->weight(FontWeight::Bold)
                            ->searchable()
                            ->sortable()
                            ->copyable()
                            ->copyMessage(__('common.copied')),

                        TextColumn::make('status')
                            ->badge()
                            ->alignEnd()
                            ->sortable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('fromWarehouse.name')
                            ->label(__('resources.transfer_requisitions.table.from'))
                            ->icon(Heroicon::BuildingOffice)
                            ->iconColor('gray')
                            ->searchable(),

                        TextColumn::make('toWarehouse.name')
                            ->label(__('resources.transfer_requisitions.table.to'))
                            ->icon(Heroicon::BuildingOffice2)
                            ->iconColor('gray')
                            ->searchable(),
                    ])->from('md'),

                    Split::make([
                        TextColumn::make('items_count')
                            ->label(__('resources.transfer_requisitions.table.items'))
                            ->counts('items')
                            ->badge()
                            ->color('gray')
                            ->numeric(),

                        TextColumn::make('requestedBy.name')
                            ->label(__('resources.transfer_requisitions.table.requested_by'))
                            ->icon(Heroicon::User)
                            ->iconColor('gray')
                            ->placeholder('—'),

                        TextColumn::make('requested_at')
                            ->label(__('resources.transfer_requisitions.table.requested'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder('—')
                            ->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                SelectFilter::make('status')->options(TransferRequisitionStatus::class),
                SelectFilter::make('from_warehouse_id')
                    ->label(__('resources.transfer_requisitions.filters.from_warehouse'))
                    ->relationship('fromWarehouse', 'name')
                    ->searchable(),
                SelectFilter::make('to_warehouse_id')
                    ->label(__('resources.transfer_requisitions.filters.to_warehouse'))
                    ->relationship('toWarehouse', 'name')
                    ->searchable(),
                TrashedFilter::make(),
                \App\Filament\Support\Filters\AdminReviewFilters::period('requested_at')
                    ->authorize('viewAuditFilters'),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (TransferRequisition $record) => $record->getUrl('view'))
            ->recordActions([
                ViewAction::make(),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Draft)
                    ->modalWidth(Width::Large),

                Action::make('submitRequest')
                    ->label(__('resources.transfer_requisitions.actions.submit'))
                    ->icon(Heroicon::PaperAirplane)
                    ->color('primary')
                    ->authorize('submitRequest')
                    ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Draft)
                    ->requiresConfirmation()
                    ->action(fn (TransferRequisition $record) => app(\App\Services\NegotiationService::class)->submitRequest($record)),

                Action::make('reviewNegotiate')
                    ->label(__('resources.transfer_requisitions.actions.review'))
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->color('warning')
                    ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                        TransferRequisitionStatus::Requested,
                        TransferRequisitionStatus::UnderReviewFulfiller,
                        TransferRequisitionStatus::UnderReviewRequestor,
                    ], true))
                    ->url(fn (TransferRequisition $record) => $record->getUrl('edit')),

                Action::make('confirm')
                    ->label(__('resources.transfer_requisitions.actions.confirm'))
                    ->icon(Heroicon::CheckBadge)
                    ->color('primary')
                    ->authorize('confirm')
                    ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                        TransferRequisitionStatus::Requested,
                        TransferRequisitionStatus::UnderReviewFulfiller,
                        TransferRequisitionStatus::UnderReviewRequestor,
                    ], true))
                    ->action(function (TransferRequisition $record) {
                        app(\App\Services\NegotiationService::class)->materializeRequestedAsApproved($record);
                        $record->update([
                            'status' => TransferRequisitionStatus::Confirmed,
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);
                    })
                    ->requiresConfirmation(),

                Action::make('dispatch')
                    ->label(__('resources.transfer_requisitions.actions.dispatch'))
                    ->icon(Heroicon::Truck)
                    ->color('primary')
                    ->authorize('dispatch')
                    ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Confirmed)
                    ->action(fn (TransferRequisition $record) => app(\App\Services\InventoryService::class)->dispatchTransfer($record))
                    ->requiresConfirmation(),

                Action::make('scanToReceive')
                    ->label(__('resources.transfer_requisitions.actions.receive'))
                    ->icon(Heroicon::QrCode)
                    ->color('success')
                    ->authorize('receive')
                    ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                        TransferRequisitionStatus::Dispatched,
                        TransferRequisitionStatus::PartiallyReceived,
                    ], true))
                    ->url(fn (TransferRequisition $record) => route('stn.scan', ['transferRequisition' => $record->id])),

                Action::make('cancel')
                    ->label(__('resources.transfer_requisitions.actions.cancel'))
                    ->icon(Heroicon::XMark)
                    ->color('danger')
                    ->authorize('cancel')
                    ->visible(fn (TransferRequisition $record) => $record->canBeCancelled())
                    ->action(fn (TransferRequisition $record) => app(\App\Services\TransferRequisitionService::class)->cancelRequisition($record))
                    ->requiresConfirmation(),

                DeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('delete')
                    ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                        TransferRequisitionStatus::Draft,
                        TransferRequisitionStatus::Cancelled,
                    ], true)),

                RestoreAction::make()->icon(Heroicon::ArrowUturnLeft)->authorize('restore'),

                ForceDeleteAction::make()
                    ->icon(Heroicon::Trash)
                    ->authorize('forceDelete')
                    ->visible(fn () => auth()->user()->isAdmin()),
            ]);
    }
}
