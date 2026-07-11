<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * The properties, constructor, and message for "a call exceeded its
 * expectation's configured maximum" — shared, by both
 * ExpectationCallLimitExceededException and
 * Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException. See
 * UnexpectedCallFields's docblock for why a trait, and why
 * TestDoubleException:: rather than self:: for the shared prose helpers.
 */
trait ExpectationCallLimitExceededFields
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

    public static function renderMessage(
        string $label,
        string $method,
        string $argumentsDescription,
        int $maximum,
        int $callNumber,
        bool $fabricated,
    ): string {
        return sprintf(
            'Test double "%s" got call #%d to "%s(%s)", but the expectation only allows %d.%s',
            $label,
            $callNumber,
            $method,
            $argumentsDescription,
            $maximum,
            TestDoubleException::fabricatedNote($fabricated),
        );
    }
}
