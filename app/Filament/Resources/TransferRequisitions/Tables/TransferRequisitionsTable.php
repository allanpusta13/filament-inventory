<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Tables;

use App\Enums\LossCategory;
use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItemRevision;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
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
                            ->label(__('resources.transfer_requisitions.table.status'))
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
                            ->placeholder(__('common.empty')),

                        TextColumn::make('requested_at')
                            ->label(__('resources.transfer_requisitions.table.requested'))
                            ->dateTime('M j, Y')
                            ->sortable()
                            ->placeholder(__('common.empty'))
                            ->alignEnd(),
                    ])->from('lg'),
                ])->space(3),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('resources.transfer_requisitions.filters.status'))
                    ->options(TransferRequisitionStatus::class),
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
                    ->visible(fn (): bool => auth()->user()?->can('viewAuditFilters', \App\Models\TransferRequisition::class) ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(12)
            ->paginated([12, 24, 48])
            ->recordUrl(fn (TransferRequisition $record) => TransferRequisitionResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::Eye),

                EditAction::make()
                    ->icon(Heroicon::PencilSquare)
                    ->authorize('update')
                    ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Draft)
                    ->modalWidth(Width::Large),

                ActionGroup::make([
                    // ── Section: Submission ───────────────────────────────────
                    ActionGroup::make([
                        Action::make('submitRequest')
                            ->label(__('resources.transfer_requisitions.actions.submit'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.submit_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.submit_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::PaperAirplane)
                            ->color('primary')
                            ->authorize('submitRequest')
                            ->visible(fn (TransferRequisition $record) => $record->status === TransferRequisitionStatus::Draft)
                            ->requiresConfirmation()
                            ->action(fn (TransferRequisition $record) => app(\App\Services\NegotiationService::class)->submitRequest($record)),
                    ])->dropdown(false),

                    // ── Section: Review & negotiation ─────────────────────────
                    ActionGroup::make([
                        Action::make('openReview')
                            ->label(__('resources.transfer_requisitions.actions.review'))
                            ->icon(Heroicon::ChatBubbleLeftRight)
                            ->color('warning')
                            ->authorize('negotiate')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true))
                            ->url(fn (TransferRequisition $record) => TransferRequisitionResource::getUrl('view', ['record' => $record])),

                        Action::make('submitRevision')
                            ->label(__('resources.transfer_requisitions.actions.propose_revision'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.propose_revision_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.propose_revision_description'))
                            ->icon(Heroicon::ChatBubbleLeftRight)
                            ->color('warning')
                            ->authorize('negotiate')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true))
                            ->modalWidth(Width::FourExtraLarge)
                            ->schema(fn (TransferRequisition $record) => TransferRequisitionForm::getRevisionFields($record))
                            ->action(function (array $data, TransferRequisition $record) {
                                $item = $record->items()->findOrFail((int) $data['transfer_requisition_item_id']);

                                app(\App\Services\NegotiationService::class)->submitRevision(
                                    item: $item,
                                    substituteVariantId: $data['substitute_product_variant_id'] ?? null,
                                    side: NegotiationSide::from($data['side']),
                                    proposedUnitName: (string) $data['proposed_unit_name'],
                                    proposedQty: (int) $data['proposed_qty'],
                                    negotiationReason: $data['negotiation_reason'] ?? null,
                                    respondsToRevisionId: $data['responds_to_revision_id'] ?? null,
                                );

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.revision_submitted'))
                                    ->success()
                                    ->send();
                            }),

                        Action::make('acceptRevision')
                            ->label(__('resources.transfer_requisitions.actions.accept_revision'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.accept_revision_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.accept_revision_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::CheckCircle)
                            ->color('success')
                            ->authorize('acceptRevision')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true) && $record->items->flatMap(fn ($item) => $item->revisions)
                                ->contains(fn ($revision) => $revision->status === RevisionStatus::Pending))
                            ->schema(fn (TransferRequisition $record) => [
                                Select::make('revision_id')
                                    ->label(__('resources.transfer_requisitions.fields.revision'))
                                    ->prefixIcon(Heroicon::CheckCircle)
                                    ->columnSpanFull()
                                    ->options(fn () => $record->items
                                        ->flatMap(fn ($item) => $item->revisions
                                            ->where('status', RevisionStatus::Pending)
                                            ->mapWithKeys(fn ($revision) => [
                                                $revision->id => "{$item->productVariant->sku}: {$revision->proposed_qty} {$revision->proposed_unit_name}",
                                            ]))
                                        ->toArray())
                                    ->required(),
                            ])
                            ->action(function (array $data, TransferRequisition $record) {
                                $revision = TransferRequisitionItemRevision::query()
                                    ->findOrFail((int) $data['revision_id']);

                                abort_unless(
                                    (int) $revision->item->transfer_requisition_id === (int) $record->id,
                                    403,
                                );

                                app(\App\Services\NegotiationService::class)->accept($revision);

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.revision_accepted'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('rejectRevision')
                            ->label(__('resources.transfer_requisitions.actions.reject_revision'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.reject_revision_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.reject_revision_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XCircle)
                            ->color('danger')
                            ->authorize('rejectRevision')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true) && $record->items->flatMap(fn ($item) => $item->revisions)
                                ->contains(fn ($revision) => $revision->status === RevisionStatus::Pending))
                            ->schema(fn (TransferRequisition $record) => [
                                Select::make('revision_id')
                                    ->label(__('resources.transfer_requisitions.fields.revision'))
                                    ->prefixIcon(Heroicon::XCircle)
                                    ->columnSpanFull()
                                    ->options(fn () => $record->items
                                        ->flatMap(fn ($item) => $item->revisions
                                            ->where('status', RevisionStatus::Pending)
                                            ->mapWithKeys(fn ($revision) => [
                                                $revision->id => "{$item->productVariant->sku}: {$revision->proposed_qty} {$revision->proposed_unit_name}",
                                            ]))
                                        ->toArray())
                                    ->required(),
                            ])
                            ->action(function (array $data, TransferRequisition $record) {
                                $revision = TransferRequisitionItemRevision::query()
                                    ->findOrFail((int) $data['revision_id']);

                                abort_unless(
                                    (int) $revision->item->transfer_requisition_id === (int) $record->id,
                                    403,
                                );

                                app(\App\Services\NegotiationService::class)->reject($revision);

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.revision_rejected'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),
                    ])->dropdown(false),

                    // ── Section: Fulfillment ──────────────────────────────────
                    ActionGroup::make([
                        Action::make('confirm')
                            ->label(__('resources.transfer_requisitions.actions.confirm'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.confirm_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.confirm_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::CheckBadge)
                            ->color('primary')
                            ->authorize('confirm')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Requested,
                                TransferRequisitionStatus::UnderReviewFulfiller,
                                TransferRequisitionStatus::UnderReviewRequestor,
                            ], true))
                            ->action(fn (TransferRequisition $record) => app(\App\Services\TransferRequisitionService::class)->confirm($record))
                            ->requiresConfirmation(),

                        Action::make('dispatch')
                            ->label(__('resources.transfer_requisitions.actions.dispatch'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.dispatch_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.dispatch_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
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
                            ->url(fn (TransferRequisition $record) => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                                'stn.scan',
                                now()->addDays(7),
                                ['transferRequisition' => $record->getKey()],
                            )),
                    ])->dropdown(false),

                    // ── Section: Loss & cancellation ──────────────────────────
                    ActionGroup::make([
                        Action::make('recordLoss')
                            ->label(__('resources.transfer_requisitions.actions.record_loss'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.record_loss_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.record_loss_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::ExclamationTriangle)
                            ->color('danger')
                            ->authorize('recordLoss')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Dispatched,
                                TransferRequisitionStatus::PartiallyReceived,
                            ], true))
                            ->modalWidth(Width::Large)
                            ->schema(fn (TransferRequisition $record) => [
                                Select::make('transfer_requisition_item_id')
                                    ->label(__('resources.loss_ledgers.fields.transfer_requisition_item'))
                                    ->prefixIcon(Heroicon::ClipboardDocumentList)
                                    ->columnSpanFull()
                                    ->options(fn () => $record->items
                                        ->mapWithKeys(fn ($item) => [
                                            $item->id => $item->productVariant->sku,
                                        ])
                                        ->toArray())
                                    ->required(),

                                TextInput::make('lost_base_qty')
                                    ->label(__('resources.loss_ledgers.fields.lost_base'))
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),

                                TextInput::make('damaged_base_qty')
                                    ->label(__('resources.loss_ledgers.fields.damaged_base'))
                                    ->prefixIcon(Heroicon::Hashtag)
                                    ->columnSpan(['default' => 1, 'md' => 1])
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),

                                Select::make('loss_category')
                                    ->label(__('resources.loss_ledgers.fields.loss_category'))
                                    ->prefixIcon(Heroicon::ExclamationTriangle)
                                    ->columnSpanFull()
                                    ->options(LossCategory::class)
                                    ->required(),

                                Textarea::make('notes')
                                    ->label(__('resources.loss_ledgers.fields.notes'))
                                    ->columnSpanFull(),
                            ])
                            ->action(function (array $data, TransferRequisition $record) {
                                app(\App\Services\InventoryService::class)->recordLoss(
                                    $record,
                                    $record->items()->findOrFail((int) $data['transfer_requisition_item_id']),
                                    (int) $data['lost_base_qty'],
                                    (int) $data['damaged_base_qty'],
                                    (string) $data['loss_category'],
                                    $data['notes'] ?? null,
                                );

                                Notification::make()
                                    ->title(__('resources.transfer_requisitions.notifications.loss_recorded'))
                                    ->success()
                                    ->send();
                            })
                            ->requiresConfirmation(),

                        Action::make('cancel')
                            ->label(__('resources.transfer_requisitions.actions.cancel'))
                            ->modalHeading(__('resources.transfer_requisitions.actions.cancel_heading'))
                            ->modalDescription(__('resources.transfer_requisitions.actions.cancel_description'))
                            ->modalSubmitActionLabel(__('actions.confirm'))
                            ->icon(Heroicon::XMark)
                            ->color('danger')
                            ->authorize('cancel')
                            ->visible(fn (TransferRequisition $record) => $record->canBeCancelled())
                            ->action(fn (TransferRequisition $record) => app(\App\Services\TransferRequisitionService::class)->cancelRequisition($record))
                            ->requiresConfirmation(),
                    ])->dropdown(false),

                    // ── Section: Destructive ──────────────────────────────────
                    ActionGroup::make([
                        DeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('delete')
                            ->visible(fn (TransferRequisition $record) => in_array($record->status, [
                                TransferRequisitionStatus::Draft,
                                TransferRequisitionStatus::Cancelled,
                            ], true)),

                        RestoreAction::make()
                            ->icon(Heroicon::ArrowUturnLeft)
                            ->authorize('restore'),

                        ForceDeleteAction::make()
                            ->icon(Heroicon::Trash)
                            ->authorize('forceDelete')
                            ->visible(fn () => auth()->user()->isAdmin()),
                    ])->dropdown(false),
                ])
                    ->icon(Heroicon::EllipsisVertical)
                    ->iconButton()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip(__('resources.transfer_requisitions.actions.more_actions'))
                    ->dropdownAutoPlacement()
                    ->dropdownWidth(Width::Large),
            ]);
    }
}