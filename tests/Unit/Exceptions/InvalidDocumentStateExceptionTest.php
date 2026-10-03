<?php

declare(strict_types=1);

use App\Exceptions\DomainErrorException;
use App\Exceptions\InvalidDocumentStateException;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TransferRequisition;

/**
 * InvalidDocumentStateException contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3: key catalogue row
 *     errors.invalid_document_state → { status, action }.
 *   - §0A.2a: lang/en/errors.php entry
 *     errors.invalid_document_state.title / .body.
 *   - §6.2 / §6.3 / §6.4 / §6.5 / §6.6 call sites across all four
 *     lifecycle services.
 *   - §18.2a / §19.1 / §19.2 positional-argument call sites.
 */
it('extends DomainErrorException', function () {
    expect(is_subclass_of(InvalidDocumentStateException::class, DomainErrorException::class))->toBeTrue();
});

it('carries the errors.invalid_document_state translation key', function () {
    $exception = new InvalidDocumentStateException(
        documentType: TransferRequisition::class,
        documentId: 7,
        actualStatus: 'draft',
        action: 'dispatch',
    );

    expect($exception->translationKey())->toBe('errors.invalid_document_state');
});

it('exposes all four constructor arguments as readonly promoted properties', function () {
    $exception = new InvalidDocumentStateException(
        documentType: TransferRequisition::class,
        documentId: 7,
        actualStatus: 'draft',
        action: 'dispatch',
    );

    expect($exception->documentType)->toBe(TransferRequisition::class);
    expect($exception->documentId)->toBe(7);
    expect($exception->actualStatus)->toBe('draft');
    expect($exception->action)->toBe('dispatch');
});

it('populates context with exactly the §6.3 key-catalogue placeholders', function () {
    // §6.3: errors.invalid_document_state → status, action.
    $exception = new InvalidDocumentStateException(
        documentType: TransferRequisition::class,
        documentId: 7,
        actualStatus: 'draft',
        action: 'dispatch',
    );

    expect($exception->context())->toBe([
        'status' => 'draft',
        'action' => 'dispatch',
    ]);
    expect(array_keys($exception->context()))->toBe(['status', 'action']);
});

it('does not leak documentType or documentId into the user-facing context', function () {
    // §6.3 key catalogue excludes both — diagnostic only. The §0A.2a
    // body template only references :action and :status.
    $exception = new InvalidDocumentStateException(
        documentType: TransferRequisition::class,
        documentId: 7,
        actualStatus: 'draft',
        action: 'dispatch',
    );

    expect($exception->context())->not->toHaveKey('document_type');
    expect($exception->context())->not->toHaveKey('document_id');
});

it('supports positional construction matching §18.2a / §19.1 / §19.2 call sites', function () {
    // §19.1 uses: new InvalidDocumentStateException(
    //     TransferRequisition::class, (int) $fresh->id,
    //     $fresh->status->value, 'confirm',
    // );
    $exception = new InvalidDocumentStateException(
        TransferRequisition::class,
        7,
        'requested',
        'confirm',
    );

    expect($exception->documentType)->toBe(TransferRequisition::class);
    expect($exception->documentId)->toBe(7);
    expect($exception->actualStatus)->toBe('requested');
    expect($exception->action)->toBe('confirm');
});

it('accepts every document model class the blueprint throws it with', function () {
    $types = [
        TransferRequisition::class,
        PurchaseOrder::class,
        SalesOrder::class,
    ];

    foreach ($types as $type) {
        $exception = new InvalidDocumentStateException($type, 1, 'draft', 'confirm');
        expect($exception->documentType)->toBe($type);
    }
});

it('accepts every action label used across §6.2–§6.6', function () {
    // §6.2 dispatchTransfer → 'dispatch'
    // §6.2 scanToReceive    → 'receive'
    // §6.2 recordLoss       → 'record_loss'
    // §6.3 submitRequest    → 'submit'
    // §6.4 orderPurchase    → 'order'
    // §6.4 receivePurchase  → 'receive'
    // §6.4 cancelPurchaseOrder → 'cancel'
    // §6.5 confirmSalesOrder → 'confirm'
    // §6.5 dispatchSale     → 'dispatch'
    // §6.5 cancelSalesOrder → 'cancel'
    // §6.6 confirm          → 'confirm'
    // §6.6 cancelRequisition → 'cancel'
    $actions = [
        'dispatch', 'receive', 'record_loss', 'submit',
        'order', 'confirm', 'cancel',
    ];

    foreach ($actions as $action) {
        $exception = new InvalidDocumentStateException(TransferRequisition::class, 1, 'draft', $action);
        expect($exception->context()['action'])->toBe($action);
    }
});

it('renders the §0A.2a body template with both placeholders', function () {
    // §0A.2a: 'Action :action is not allowed while status is :status.'
    $exception = new InvalidDocumentStateException(
        TransferRequisition::class,
        7,
        'draft',
        'dispatch',
    );

    $body = __('errors.invalid_document_state.body', $exception->context());

    expect($body)->toContain('dispatch');
    expect($body)->toContain('draft');
    expect($body)->not->toContain(':action');
    expect($body)->not->toContain(':status');
});

it('resolves the .title translation key', function () {
    $title = __('errors.invalid_document_state.title');

    expect($title)->toBeString()->not->toBe('');
    expect($title)->not->toBe('errors.invalid_document_state.title');
});

it('is caught by a catch(\\DomainErrorException) block', function () {
    $caught = false;

    try {
        throw new InvalidDocumentStateException(TransferRequisition::class, 1, 'draft', 'dispatch');
    } catch (DomainErrorException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});
