<?php

declare(strict_types=1);

use App\Exceptions\DomainErrorException;
use App\Exceptions\NegotiationNotAllowedException;

/**
 * NegotiationNotAllowedException contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3: "class NegotiationNotAllowedException extends
 *     DomainErrorException {}".
 *   - §6.3 key catalogue — two distinct keys, two distinct contexts.
 *   - §6.3 assertNegotiable() / submitRevision() call sites.
 *   - §0A.2a: both errors.* entries must resolve their .title and .body.
 */
it('extends DomainErrorException', function () {
    expect(is_subclass_of(NegotiationNotAllowedException::class, DomainErrorException::class))->toBeTrue();
});

it('has no overridden constructor — uses the base __construct directly', function () {
    // §6.3: bare subclass carrying two distinct keys via caller-
    // supplied arguments.
    $reflection = new ReflectionClass(NegotiationNotAllowedException::class);

    expect($reflection->getConstructor()->getDeclaringClass()->getName())
        ->toBe(DomainErrorException::class);
});

it('carries errors.negotiation_not_allowed when the parent status is wrong', function () {
    // §6.3 NegotiationService::assertNegotiable() / submitRevision():
    // new NegotiationNotAllowedException(
    //     'errors.negotiation_not_allowed',
    //     ['requisition' => (int) $parent->id, 'status' => $parent->status->value],
    // );
    $exception = new NegotiationNotAllowedException(
        'errors.negotiation_not_allowed',
        ['requisition' => 7, 'status' => 'confirmed'],
    );

    expect($exception->translationKey())->toBe('errors.negotiation_not_allowed');
    expect($exception->context())->toBe(['requisition' => 7, 'status' => 'confirmed']);
});

it('carries errors.revision_already_resolved when the revision is not pending', function () {
    // §6.3 NegotiationService::assertNegotiable():
    // new NegotiationNotAllowedException(
    //     'errors.revision_already_resolved',
    //     ['revision' => (int) $revision->id],
    // );
    $exception = new NegotiationNotAllowedException(
        'errors.revision_already_resolved',
        ['revision' => 42],
    );

    expect($exception->translationKey())->toBe('errors.revision_already_resolved');
    expect($exception->context())->toBe(['revision' => 42]);
});

it('resolves .title and .body for both §6.3 keys', function (string $key) {
    $exception = new NegotiationNotAllowedException($key, []);

    $title = __($key.'.title');
    $body = __($key.'.body');

    expect($title)->not->toBe($key.'.title');
    expect($title)->not->toBe('');
    expect($body)->not->toBe($key.'.body');
    expect($body)->not->toBe('');
})->with([
    'errors.negotiation_not_allowed',
    'errors.revision_already_resolved',
]);

it('renders the errors.negotiation_not_allowed body with both placeholders', function () {
    // §0A.2a: 'Requisition :requisition cannot be negotiated while :status.'
    $exception = new NegotiationNotAllowedException(
        'errors.negotiation_not_allowed',
        ['requisition' => 7, 'status' => 'confirmed'],
    );

    $body = __('errors.negotiation_not_allowed.body', $exception->context());

    expect($body)->toContain('7');
    expect($body)->toContain('confirmed');
    expect($body)->not->toContain(':requisition');
    expect($body)->not->toContain(':status');
});

it('renders the errors.revision_already_resolved body with the placeholder', function () {
    // §0A.2a: 'Revision :revision has already been resolved.'
    $exception = new NegotiationNotAllowedException(
        'errors.revision_already_resolved',
        ['revision' => 42],
    );

    $body = __('errors.revision_already_resolved.body', $exception->context());

    expect($body)->toContain('42');
    expect($body)->not->toContain(':revision');
});

it('defaults context to an empty array when omitted', function () {
    $exception = new NegotiationNotAllowedException('errors.negotiation_not_allowed');

    expect($exception->context())->toBe([]);
});

it('is caught by a catch(\\DomainErrorException) block', function () {
    $caught = false;

    try {
        throw new NegotiationNotAllowedException(
            'errors.negotiation_not_allowed',
            ['requisition' => 1, 'status' => 'draft'],
        );
    } catch (DomainErrorException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});

it('is caught by a catch(\\DomainException) block', function () {
    $caught = false;

    try {
        throw new NegotiationNotAllowedException(
            'errors.revision_already_resolved',
            ['revision' => 1],
        );
    } catch (DomainException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});
