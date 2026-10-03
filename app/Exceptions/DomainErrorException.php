<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Abstract base for every typed domain exception.
 *
 * Blueprint §6.3 (exception contract): every domain failure is a typed
 * exception carrying a stable translation key plus machine-readable
 * context. The presentation layer resolves
 * `__($e->translationKey() . '.title', $e->context())` and
 * `__($e->translationKey() . '.body', $e->context())` (see §0A.10),
 * so domain services never embed English UI copy.
 *
 * Extends \DomainException so existing `catch (\DomainException)`
 * handling keeps working (§6.3 contract), and therefore also
 * \LogicException / \Exception.
 *
 * The key catalogue is enumerated in §6.3; every key it lists lives
 * under `lang/{locale}/errors.php` with `.title` and `.body` entries
 * (§0A.10, §25).
 *
 * Concrete subclasses in the blueprint's file map (§25):
 *   - InsufficientStockException
 *   - OutstandingQuantityExceededException
 *   - InvalidDocumentStateException
 *   - InvalidRevisionTransitionException
 *   - ProductFamilyHasVariantsException
 *   - DomainRuleViolationException
 *   - NegotiationNotAllowedException
 */
abstract class DomainErrorException extends DomainException
{
    public function __construct(
        private readonly string $translationKey,
        private readonly array $context = [],
    ) {
        parent::__construct($translationKey);
    }

    final public function translationKey(): string
    {
        return $this->translationKey;
    }

    /** @return array<string, mixed> */
    final public function context(): array
    {
        return $this->context;
    }
}
