<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\Diagnostic;

/**
 * Every concrete subclass holds its own diagnostic fields directly and
 * renders its own getMessage() — there is no separate parallel Diagnostic
 * data class per exception type (see ARCHITECTURE.md, "Module boundaries").
 * getMessage() gives full human prose with zero setup in any test runner.
 * getDiagnostic() gives structured access to the same instance for anything
 * that wants it (e.g. the future PHPUnit ComparisonFailure integration, see
 * ARCHITECTURE.md's "PHPUnit integration").
 */
abstract class TestDoubleException extends \RuntimeException implements Diagnostic
{
    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }

    /**
     * Shared by every subclass whose diagnostic can fire on a Loose-mode
     * fabricated stand-in (see ARCHITECTURE.md, "Modes: Loose, Strict,
     * Passthru" — "mandatory provenance tagging on every fabricated
     * object"). Returns '' when not fabricated so every render() can
     * unconditionally splice this into its sprintf. Static (not just
     * protected) so the PHPUnit-specific exception variants under
     * Integrations\PHPUnit — which cannot extend these classes, see
     * ARCHITECTURE.md's "PHPUnit integration" — can reuse the exact same
     * note without duplicating it. Deliberately doesn't cite ARCHITECTURE.md
     * in the note itself: that document is written for whoever picks up
     * this library's own implementation, not for someone whose test just
     * failed — the two have different audiences even though the same
     * codebase's docblocks cite it constantly.
     */
    final public static function fabricatedNote(bool $fabricated): string
    {
        if (! $fabricated) {
            return '';
        }

        return ' Note: this double was auto-fabricated by Loose mode, not created directly.';
    }

    /**
     * A best-effort variable name for a code snippet in a message, derived
     * from a double's label (e.g. "SecondLink" -> "secondLink"). Labels
     * aren't always valid identifier fragments — an intersection-typed
     * fabrication (see DoubleState::targetCandidates()) has a label like
     * "Fillable&Sized" — so non-identifier characters are stripped rather
     * than trusted verbatim. This is illustrative code in a message, not
     * executed, but it should still read as something a person could
     * plausibly paste in.
     */
    final public static function suggestedVariableName(string $label): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $label) ?? '';

        return $sanitized === '' || preg_match('/^[0-9]/', $sanitized) === 1
            ? 'double'
            : lcfirst($sanitized);
    }
}
