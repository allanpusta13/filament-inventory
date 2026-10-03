<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when an action is attempted against a document whose current
 * lifecycle status does not permit that action.
 *
 * Blueprint §6.3 (exception contract).
 *
 * Translation key: `errors.invalid_document_state`
 * Context: status, action
 *
 * Thrown from (blueprint §6):
 *   - InventoryService::dispatchTransfer()      — action 'dispatch'
 *     (requires Confirmed, §19.3)
 *   - InventoryService::scanToReceive()         — action 'receive'
 *     (requires Dispatched | PartiallyReceived, §6.2)
 *   - InventoryService::recordLoss()            — action 'record_loss'
 *     (requires Dispatched | PartiallyReceived, §6.2)
 *   - NegotiationService::submitRequest()       — action 'submit'
 *     (requires Draft, §6.3)
 *   - PurchaseService::orderPurchase()          — action 'order'
 *     (requires Draft, §6.4)
 *   - PurchaseService::receivePurchase()        — action 'receive'
 *     (requires Ordered | PartiallyReceived, §6.4)
 *   - PurchaseService::cancelPurchaseOrder()    — action 'cancel'
 *     (requires canBeCancelled(), §6.4 / §19.4)
 *   - SalesService::confirmSalesOrder()         — action 'confirm'
 *     (requires Draft, §6.5)
 *   - SalesService::dispatchSale()              — action 'dispatch'
 *     (requires Confirmed | PartiallyDispatched, §6.5)
 *   - SalesService::cancelSalesOrder()          — action 'cancel'
 *     (requires canBeCancelled(), §6.5)
 *   - TransferRequisitionService::confirm()     — action 'confirm'
 *     (requires Requested | UnderReviewFulfiller | UnderReviewRequestor,
 *      §6.6 / §19.1)
 *   - TransferRequisitionService::cancelRequisition() — action 'cancel'
 *     (requires canBeCancelled(), §6.6 / §19.2)
 *
 * Presentation layer (§0A.10):
 *   Notification::make()
 *       ->danger()
 *       ->title(__('errors.invalid_document_state.title'))
 *       ->body(__('errors.invalid_document_state.body', $e->context()))
 *       ->send();
 *
 * The context keys intentionally mirror the §6.3 key-catalogue row
 * `errors.invalid_document_state → status, action` so that
 * `lang/{locale}/errors.php` `errors.invalid_document_state.body`
 * can interpolate `:action` and `:status` directly, per the §0A.2a
 * canonical template:
 *     'Action :action is not allowed while status is :status.'
 *
 * `documentType` and `documentId` are stored separately so callers
 * can branch on the concrete model class (TransferRequisition,
 * PurchaseOrder, SalesOrder) without re-parsing the exception, and
 * so log records can include the offending document id without
 * additional context lookups. Neither is interpolated into the
 * user-facing translation body — they are diagnostic only.
 *
 * Extends DomainErrorException (§6.3) so the type is both a
 * \DomainException (existing catch blocks keep working) and carries
 * the stable translation key + machine context for the presentation
 * layer to resolve.
 */
class InvalidDocumentStateException extends DomainErrorException
{
    public function __construct(
        public readonly string $documentType,
        public readonly int $documentId,
        public readonly string $actualStatus,
        public readonly string $action,
    ) {
        parent::__construct('errors.invalid_document_state', [
            'status' => $actualStatus,
            'action' => $action,
        ]);
    }
}
