<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when a call matches a configured expectation, but accepting it
 * would exceed that expectation's configured maximum call count (e.g. a
 * fourth call to something configured with ->times(3), or any call at all
 * to something configured with ->never()).
 */
class ExpectationCallLimitExceededException extends TestDoubleException
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly int $maximum,
        public readonly int $callNumber,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $method, $argumentsDescription, $maximum, $callNumber, $fabricated));
    }

    /**
     * Static (not just private) so PHPUnitExpectationCallLimitExceededException
     * — which cannot extend this class, see ARCHITECTURE.md's "PHPUnit
     * integration" — renders byte-identical prose without duplicating the
     * sprintf.
     */
    public static function renderMessage(
        string $label,
        string $method,
        string $argumentsDescription,
        int $maximum,
        int $callNumber,
        bool $fabricated,
    ): string {
        return sprintf(
            'Test double "%s" received call #%d to "%s(%s)", but the matching expectation '
            .'allows at most %d call(s).%s',
            $label,
            $callNumber,
            $method,
            $argumentsDescription,
            $maximum,
            self::fabricatedNote($fabricated),
        );
    }
}
