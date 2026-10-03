<?php

declare(strict_types=1);

use App\Enums\RevisionStatus;
use App\Exceptions\DomainErrorException;
use App\Exceptions\InvalidRevisionTransitionException;

/**
 * InvalidRevisionTransitionException contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3: key catalogue row
 *     errors.invalid_revision_transition → { actual, target }.
 *   - §0A.2a: lang/en/errors.php entry
 *     errors.invalid_revision_transition.title / .body.
 *   - §3.9 ensureCanTransitionTo() call sites.
 *   - §6.3 NegotiationService::accept / reject call sites (via the
 *     model guard).
 */
it('extends DomainErrorException', function () {
    expect(is_subclass_of(InvalidRevisionTransitionException::class, DomainErrorException::class))->toBeTrue();
});

it('carries the errors.invalid_revision_transition translation key', function () {
    $exception = new InvalidRevisionTransitionException('accepted', 'pending');

    expect($exception->translationKey())->toBe('errors.invalid_revision_transition');
});

it('exposes actualStatus and targetStatus as readonly promoted properties', function () {
    $exception = new InvalidRevisionTransitionException('accepted', 'pending');

    expect($exception->actualStatus)->toBe('accepted');
    expect($exception->targetStatus)->toBe('pending');
});

it('populates context with exactly the §6.3 key-catalogue placeholders', function () {
    // §6.3: errors.invalid_revision_transition → actual, target.
    $exception = new InvalidRevisionTransitionException('accepted', 'pending');

    expect($exception->context())->toBe([
        'actual' => 'accepted',
        'target' => 'pending',
    ]);
    expect(array_keys($exception->context()))->toBe(['actual', 'target']);
});

it('accepts every RevisionStatus->value across the constructor', function (RevisionStatus $actual, RevisionStatus $target) {
    // §3.9 passes $this->status->value and $target->value — string
    // backing values, never enum instances.
    $exception = new InvalidRevisionTransitionException($actual->value, $target->value);

    expect($exception->actualStatus)->toBe($actual->value);
    expect($exception->targetStatus)->toBe($target->value);
})->with([
    ['actual' => RevisionStatus::Accepted, 'target' => RevisionStatus::Pending],
    ['actual' => RevisionStatus::Rejected, 'target' => RevisionStatus::Pending],
    ['actual' => RevisionStatus::Pending,  'target' => RevisionStatus::Pending],
    ['actual' => RevisionStatus::Accepted, 'target' => RevisionStatus::Rejected],
]);

it('stores string values, not enum instances, in the context array', function () {
    // Context must remain serializable and translatable across every
    // configured locale (§0A.15). Storing enums would break both.
    $exception = new InvalidRevisionTransitionException('accepted', 'pending');

    foreach ($exception->context() as $value) {
        expect($value)->toBeString();
    }
});

it('supports positional construction matching §3.9 call sites', function () {
    // §3.9: new InvalidRevisionTransitionException($this->status->value, $target->value)
    $exception = new InvalidRevisionTransitionException('accepted', 'pending');

    expect($exception->actualStatus)->toBe('accepted');
    expect($exception->targetStatus)->toBe('pending');
});

it('renders the §0A.2a body template with both placeholders', function () {
    // §0A.2a: 'Cannot move revision from :actual to :target.'
    $exception = new InvalidRevisionTransitionException('accepted', 'pending');

    $body = __('errors.invalid_revision_transition.body', $exception->context());

    expect($body)->toContain('accepted');
    expect($body)->toContain('pending');
    expect($body)->not->toContain(':actual');
    expect($body)->not->toContain(':target');
});

it('resolves the .title translation key', function () {
    $title = __('errors.invalid_revision_transition.title');

    expect($title)->toBeString()->not->toBe('');
    expect($title)->not->toBe('errors.invalid_revision_transition.title');
});

it('is caught by a catch(\\DomainErrorException) block', function () {
    $caught = false;

    try {
        throw new InvalidRevisionTransitionException('accepted', 'pending');
    } catch (DomainErrorException $e) {
        $caught = true;
    }

    expect($caught)->toBeTrue();
});
