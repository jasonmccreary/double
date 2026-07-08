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
     * unconditionally splice this into its sprintf.
     */
    final protected function fabricatedNote(bool $fabricated): string
    {
        if (! $fabricated) {
            return '';
        }

        return ' This double was auto-fabricated as a safe-default stand-in by Loose mode, '
            .'not created directly via TestDouble::for() — see ARCHITECTURE.md\'s '
            .'"Modes: Loose, Strict, Passthru."';
    }
}
