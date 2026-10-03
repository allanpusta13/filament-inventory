<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a negotiation operation is attempted against a
 * requisition item revision whose parent requisition status does not
 * permit negotiation, or whose revision has already been resolved.
 *
 * Blueprint §6.3 (exception contract) — one of the concrete
 * "key + context carrier" exceptions with no dedicated constructor:
 * the caller supplies the translation key and the machine context.
 *
 * Extends DomainErrorException directly (no overridden constructor),
 * matching §6.3:
 *
 *     class NegotiationNotAllowedException extends DomainErrorException {}
 *
 * Two distinct translation keys are used with this exception type,
 * distinguished by the §6.3 key-catalogue:
 *
 *   1. `errors.negotiation_not_allowed`
 *      Context: requisition, status
 *      Thrown from `NegotiationService::assertNegotiable()` (§6.3) and
 *      `NegotiationService::submitRevision()` (§6.3) when the parent
 *      requisition is not in a negotiable status (Requested |
 *      UnderReviewFulfiller | UnderReviewRequestor):
 *
 *          throw new NegotiationNotAllowedException(
 *              'errors.negotiation_not_allowed',
 *              ['requisition' => (int) $parent->id, 'status' => $parent->status->value],
 *          );
 *
 *   2. `errors.revision_already_resolved`
 *      Context: revision
 *      Thrown from `NegotiationService::assertNegotiable()` (§6.3)
 *      when the revision itself is not in Pending status:
 *
 *          throw new NegotiationNotAllowedException(
 *              'errors.revision_already_resolved',
 *              ['revision' => (int) $revision->id],
 *          );
 *
 * Presentation layer (§0A.10) resolves the key + context through the
 * standard typed-exception contract:
 *
 *   Notification::make()
 *       ->danger()
 *       ->title(__($e->translationKey() . '.title'))
 *       ->body(__($e->translationKey() . '.body', $e->context()))
 *       ->send();
 *
 * The relevant §0A.2a `lang/en/errors.php` entries are:
 *
 *   'negotiation_not_allowed' => [
 *       'title' => 'Negotiation not allowed',
 *       'body'  => 'Requisition :requisition cannot be negotiated while :status.',
 *   ],
 *   'revision_already_resolved' => [
 *       'title' => 'Revision already resolved',
 *       'body'  => 'Revision :revision has already been resolved.',
 *   ],
 *
 * Extends DomainErrorException (§6.3) so the type is both a
 * \DomainException (existing catch blocks keep working) and carries
 * the stable translation key + machine context for the presentation
 * layer to resolve. Because §6.3 registers this type in the key
 * catalogue twice with different keys, the class intentionally does
 * not hard-code a single key in its constructor — the caller
 * supplies both the key and the matching context array.
 */
class NegotiationNotAllowedException extends DomainErrorException {}
