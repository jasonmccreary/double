<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * Thrown by TestDouble::verify() when one or more expects() expectations
 * were never satisfied by the end of the test.
 */
final class UnsatisfiedExpectationsDiagnostic implements Diagnostic
{
    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public function __construct(
        public readonly string $label,
        public readonly array $expectations,
        public readonly bool $fabricated = false,
    ) {}
}
