<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\Diagnostic;

/**
 * Every concrete subclass holds its own diagnostic fields directly and
 * renders its own getMessage() — there is no separate parallel Diagnostic
 * data class per exception type. getMessage() gives full human prose with
 * zero setup in any test runner. getDiagnostic() gives structured access to
 * the same instance for anything that wants it.
 *
 * Every concrete subclass's public readonly fields are frozen, semver-
 * guaranteed public API, same freeze as the Matcher interface. Adding a
 * field is a minor-version change; renaming, removing, or retyping one is a
 * major-version change, same as any other public API in this library.
 */
abstract class TestDoubleException extends \RuntimeException implements Diagnostic
{
    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }

    /**
     * Shared by every subclass whose diagnostic can fire on a Loose-mode
     * fabricated stand-in (mandatory provenance tagging on every fabricated
     * object, so a person inspecting one can tell it's a stand-in rather
     * than guessing why a value looks wrong). Returns '' when not fabricated
     * so every render() can unconditionally splice this into its sprintf.
     * Static (not just protected) so the PHPUnit-specific exception variants
     * under Integrations\PHPUnit — which cannot extend these classes, since
     * they already extend PHPUnit's own AssertionFailedError — can reuse the
     * exact same note without duplicating it.
     */
    final public static function fabricatedNote(bool $fabricated): string
    {
        if (! $fabricated) {
            return '';
        }

        return "\n\nNote: this test double was returned automatically. You will need to configure it "
            .'explicitly if you want it to behave differently.';
    }

    /**
     * Joins $message with fabricatedNote()'s own "\n\n"-prefixed text — used
     * instead of plain concatenation by every renderMessage() whose $message
     * can itself already end in "\n" (a call-correlation paragraph, see
     * CallListFormatter::renderCorrelationParagraph()). Plain concatenation
     * in that case would leave a stray blank line between the correlation
     * paragraph and the note; trimming unconditionally would instead wrongly
     * swallow the correlation paragraph's own deliberate trailing newline on
     * messages with no note to append.
     */
    final public static function appendFabricatedNote(string $message, bool $fabricated): string
    {
        $note = self::fabricatedNote($fabricated);

        return $note === '' ? $message : rtrim($message, "\n").$note;
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
