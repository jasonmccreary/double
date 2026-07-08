<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\CallLimitExceededDiagnostic;

/**
 * Thrown when a call matches a configured expectation, but accepting it
 * would exceed that expectation's configured maximum call count (e.g. a
 * fourth call to something configured with ->times(3), or any call at all
 * to something configured with ->never()).
 */
final class ExpectationCallLimitExceededException extends TestDoubleException
{
    public static function forExpectation(
        string $label,
        string $method,
        string $argumentsDescription,
        int $maximum,
        int $callNumber,
        bool $fabricated = false,
    ): self {
        return new self(new CallLimitExceededDiagnostic($label, $method, $argumentsDescription, $maximum, $callNumber, $fabricated));
    }
}
