<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Diagnostics\UnsatisfiedExpectationsDiagnostic;

/**
 * Thrown by TestDouble::verify() when one or more expects() expectations
 * were never satisfied by the end of the test. Each UnsatisfiedExpectation
 * is paired with every other call actually observed for that method — see
 * ARCHITECTURE.md, "Correlating unsatisfied expectations with actual
 * observed calls."
 */
final class UnsatisfiedExpectationException extends TestDoubleException
{
    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public static function forUnmet(string $label, array $expectations): self
    {
        return new self(new UnsatisfiedExpectationsDiagnostic($label, $expectations));
    }
}
