<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a transfer requisition item revision is asked to
 * transition to a status that is not legal from its current status.
 *
 * Blueprint §6.3 (exception contract).
 *
 * Translation key: `errors.invalid_revision_transition`
 * Context: actual, target
 *
 * The guard lives on the model (§3.9
 * `TransferRequisitionItemRevision::ensureCanTransitionTo()`), which
 * enforces the single-move-out-of-`Pending` rule:
 *   - Pending  → Accepted  ✔ legal
 *   - Pending  → Rejected  ✔ legal
 *   - Pending  → Pending   ✘ illegal (no self-transition)
 *   - Accepted → any       ✘ illegal (resolved)
 *   - Rejected → any       ✘ illegal (resolved)
 *
 * Called from (blueprint §6.3):
 *   - NegotiationService::accept() — target Accepted, after
 *     `assertNegotiable()` has confirmed the parent requisition is
 *     in a negotiable status and the revision is still Pending.
 *   - NegotiationService::reject() — target Rejected, same preconditions.
 *
 * Presentation layer (§0A.10):
 *   Notification::make()
 *       ->danger()
 *       ->title(__('errors.invalid_revision_transition.title'))
 *       ->body(__('errors.invalid_revision_transition.body', $e->context()))
 *       ->send();
 *
 * The context keys intentionally mirror the §6.3 key-catalogue row
 * `errors.invalid_revision_transition → actual, target` so that
 * `lang/{locale}/errors.php` `errors.invalid_revision_transition.body`
 * can interpolate `:actual` and `:target` directly, per the §0A.2a
 * canonical template:
 *     'Cannot move revision from :actual to :target.'
 *
 * `actualStatus` and `targetStatus` are stored as readonly promoted
 * properties so callers that want to branch on the concrete enum
 * values can do so without re-parsing the string context. The context
 * array carries the raw string values (`RevisionStatus->value`), not
 * the enum instances, so the array remains serializable and
 * translatable across every configured locale (§0A.15).
 *
 * Extends DomainErrorException (§6.3) so the type is both a
 * \DomainException (existing catch blocks keep working) and carries
 * the stable translation key + machine context for the presentation
 * layer to resolve.
 */
class InvalidRevisionTransitionException extends DomainErrorException
{
    public function __construct(
        public readonly string $actualStatus,
        public readonly string $targetStatus,
    ) {
        parent::__construct('errors.invalid_revision_transition', [
            'actual' => $actualStatus,
            'target' => $targetStatus,
        ]);
    }
}
