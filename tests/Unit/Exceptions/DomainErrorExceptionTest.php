<?php

declare(strict_types=1);

use App\Exceptions\DomainErrorException;

/**
 * DomainErrorException base-class contract tests.
 *
 * NOTE: This test file is NOT defined by the blueprint's §12 Pest
 * coverage list or §25 file map. It is a companion test authored at
 * owner direction.
 *
 * Blueprint anchors exercised:
 *   - §6.3 exception contract: abstract base carrying a translation
 *     key + machine context, extending \DomainException so existing
 *     catch blocks keep working.
 *   - §0A.10: presentation layer resolves
 *     __($e->translationKey() . '.title'|'.body', $e->context()).
 */
it('is abstract — the base class cannot be instantiated directly', function () {
    // §6.3: every thrown instance is a concrete subclass.
    $reflection = new ReflectionClass(DomainErrorException::class);

    expect($reflection->isAbstract())->toBeTrue();
});

it('extends \\DomainException so existing catch blocks keep working', function () {
    // §6.3 contract: "All types extend \DomainException (directly or via
    // the base below) so existing catch (\DomainException) handling keeps
    // working." The Livewire ScanForm catch block (§21.1) relies on this.
    expect(is_subclass_of(DomainErrorException::class, DomainException::class))->toBeTrue();
    expect(is_subclass_of(DomainErrorException::class, LogicException::class))->toBeTrue();
    expect(is_subclass_of(DomainErrorException::class, Exception::class))->toBeTrue();
});

it('exposes translationKey() and context() as the canonical accessors', function () {
    // A minimal anonymous subclass exercises the base-class surface
    // without depending on any typed subclass.
    $exception = new class('errors.some_key', ['foo' => 'bar']) extends DomainErrorException {};

    expect($exception->translationKey())->toBe('errors.some_key');
    expect($exception->context())->toBe(['foo' => 'bar']);
});

it('defaults context() to an empty array when no context is supplied', function () {
    $exception = new class('errors.some_key') extends DomainErrorException {};

    expect($exception->context())->toBe([]);
});

it('stores the translation key as the exception message', function () {
    // §6.3: parent::__construct($translationKey). The message is the
    // stable key, not human copy, so framework-level logging surfaces
    // the key rather than untranslated English.
    $exception = new class('errors.some_key', ['x' => 1]) extends DomainErrorException {};

    expect($exception->getMessage())->toBe('errors.some_key');
});

it('accepts arbitrary machine-readable context shapes', function () {
    // §6.3 key catalogue shows context arrays with 0–4 keys depending on
    // the caller — the base class does not narrow the shape.
    $shapes = [
        [],
        ['item' => 42],
        ['index' => 0, 'variant' => 7],
        ['from' => 1, 'to' => 2],
        ['requisition' => 5, 'status' => 'requested'],
    ];

    foreach ($shapes as $shape) {
        $exception = new class('errors.any_key', $shape) extends DomainErrorException {};
        expect($exception->context())->toBe($shape);
    }
});

it('is caught by a catch(\\DomainException) block', function () {
    // The load-bearing compatibility guarantee from §6.3.
    $caught = false;

    try {
        throw new class('errors.any_key') extends DomainErrorException {};
    } catch (DomainException $e) {
        $caught = true;
        expect($e)->toBeInstanceOf(DomainErrorException::class);
    }

    expect($caught)->toBeTrue();
});
