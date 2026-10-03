<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Concrete key + context carrier for caller/precondition violations
 * that do not have a dedicated typed exception.
 *
 * Blueprint §6.3 (exception contract):
 *
 *     class DomainRuleViolationException extends DomainErrorException {}
 *
 * This is the generic workhorse of the exception layer. Every
 * precondition failure that is not one of the six typed subclasses
 * throws this class with an explicit translation key from the §6.3
 * key catalogue and a matching machine-readable context array. There
 * is no overridden constructor — the base DomainErrorException
 * `__construct(string $translationKey, array $context = [])`
 * contract is used directly.
 *
 * Callers construct it as:
 *
 *     throw new DomainRuleViolationException(
 *         'errors.<key>',
 *         [ ':<placeholder>' => $value, ... ],
 *     );
 *
 * The presentation layer (§0A.10) then resolves the key + context
 * through the standard typed-exception path:
 *
 *   Notification::make()
 *       ->danger()
 *       ->title(__($e->translationKey() . '.title'))
 *       ->body(__($e->translationKey() . '.body', $e->context()))
 *       ->send();
 *
 * The §6.3 key catalogue enumerates every key carried by this class.
 * The keys fall into four functional families:
 *
 *   Movement / unit preconditions (§6.2 InventoryService::recordMovement,
 *   directTransfer, adjustment):
 *     - errors.invalid_movement_type        { type }
 *     - errors.invalid_unit_ratio           { ratio } (+ index when per-line)
 *     - errors.same_warehouse_transfer      { warehouse }
 *
 *   Transfer / purchase / sales payload shape (§6.2 directTransfer,
 *   §6.4 PurchaseService, §6.5 SalesService):
 *     - errors.empty_transfer_items         ( — )
 *     - errors.duplicate_transfer_variant   { index, variant }
 *     - errors.empty_purchase_items         ( — )
 *     - errors.empty_purchase_receipt       ( — )
 *     - errors.empty_sales_items            ( — )
 *     - errors.empty_sales_dispatch         ( — )
 *     - errors.empty_requisition_items      ( — )
 *     - errors.invalid_return_quantity      { item, qty }
 *     - errors.missing_item_field           { index, field }
 *     - errors.invalid_item_quantity        { index, qty }
 *
 *   Reference resolution (§6.2 directTransfer, scanToReceive, §6.3
 *   NegotiationService::submitRevision):
 *     - errors.unknown_variant              { variant }
 *     - errors.undefined_unit               { unit, variant } (+ index)
 *     - errors.unit_ratio_mismatch          { index, unit }
 *     - errors.unknown_requisition_item     { item }
 *     - errors.cross_item_revision          { revision, item }
 *
 *   Scan payload / loss / negotiation preconditions (§6.2
 *   scanToReceive, recordLoss, §6.3 submitRevision):
 *     - errors.non_integer_payload          { item }
 *     - errors.negative_payload             { item }
 *     - errors.empty_loss                   { item }
 *     - errors.invalid_proposed_quantity    { qty }
 *     - errors.missing_approved_quantity    { requisition } or { item }
 *
 *   Operational scope / derived identifiers (§6.2 directTransfer,
 *   adjustment, §18.2a CreateWarehouse):
 *     - errors.warehouse_out_of_scope       { from, to }
 *     - errors.warehouse_code_exhausted     { name }
 *
 * The corresponding `lang/{locale}/errors.php` entries carry `.title`
 * and `.body` per §0A.10 and the §0A.2a canonical catalogue. Both the
 * key and the context are supplied by the caller, matching the §6.3
 * design intent: this exception is a generic carrier, not a fixed
 * semantic type.
 *
 * Extends DomainErrorException (§6.3), which extends \DomainException.
 * Handled by the same `catch (\App\Exceptions\DomainErrorException $e)`
 * block in `app/Livewire/Stn/ScanForm.php` (§21.1) as every other typed
 * domain exception, and by any other generic domain-exception handler
 * in the application.
 *
 * Note: unlike the six typed subclasses (InsufficientStockException,
 * OutstandingQuantityExceededException, InvalidDocumentStateException,
 * InvalidRevisionTransitionException, ProductFamilyHasVariantsException)
 * which pin a single translation key in their own constructor, this
 * class is the "generic key + context carrier" — same role as
 * NegotiationNotAllowedException, but for the caller/precondition
 * family rather than the negotiation family.
 */
class DomainRuleViolationException extends DomainErrorException {}
