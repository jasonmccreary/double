<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

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
    ): self {
        return new self(sprintf(
            'Test double "%s" received call #%d to "%s(%s)", but the matching expectation '
            .'allows at most %d call(s).',
            $label,
            $callNumber,
            $method,
            $argumentsDescription,
            $maximum,
        ));
    }
}
