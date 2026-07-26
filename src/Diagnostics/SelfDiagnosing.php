<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * Fills in getDiagnostic() for classes that can't get it for free from
 * TestDoubleException — PHPUnit exceptions' single inheritance slot is
 * already spent extending AssertionFailedError.
 */
trait SelfDiagnosing
{
    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }
}
