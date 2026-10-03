<?php

declare(strict_types=1);

namespace App\Filament\Resources\TransferRequisitions\Pages;

use App\Enums\NegotiationSide;
use App\Enums\RevisionStatus;
use App\Enums\TransferRequisitionStatus;
use App\Filament\Resources\TransferRequisitions\Schemas\TransferRequisitionForm;
use App\Filament\Resources\TransferRequisitions\TransferRequisitionResource;
use App\Models\TransferRequisition;
use App\Models\TransferRequisitionItemRevision;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * ViewTransferRequisition — §18.2a canonical contract.
 *
 * Negotiation states are NOT editable (TransferRequisitionPolicy::update
 * permits Draft only), so `submitRevision` / `acceptRevision` /
 * `rejectRevision` modals live here as header actions. The table's
 * `openReview` action links to this view page for negotiation states.
 */
class ViewTransferRequisition extends ViewRecord
{
    protected static string $resource = TransferRequisitionResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
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
        ];
    }
}
