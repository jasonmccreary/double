<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * getDiagnostic(): Diagnostic { return $this; } — the one line every
 * Integrations\PHPUnit\PHPUnitXxxException has to repeat for itself, since
 * it can't get this for free the way a plain TestDoubleException does
 * (single inheritance is already spent on extending PHPUnit's own
 * AssertionFailedError). Lives here, not in
 * Integrations\PHPUnit, so it stays usable by any future non-exception
 * Diagnostic producer too, and so it can be a zero-dependency addition to
 * this already-zero-dependency module (see Diagnostic's own docblock).
 */
trait SelfDiagnosing
{
    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }
}
