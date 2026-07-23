<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\Pluralizer;

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
        $message = sprintf(
            'Test double `%s` received %s to `%s(%s)`, but your expectation only allowed %s.',
            $label,
            Pluralizer::pluralize($callNumber, 'call', 'calls'),
            $method,
            $argumentsDescription,
            Pluralizer::pluralize($maximum, 'call', 'calls'),
        );

        if (! $fabricated) {
            return $message;
        }

        return $message.sprintf(
            ' Note: this test double was returned automatically from an unconfigured call, so if %s %s '
            .'here are legitimate, loosen the expectation — ->times(%d) or ->atLeastOnce() — rather than '
            .'the default of exactly %s.',
            self::numberWord($callNumber),
            $callNumber === 1 ? 'call' : 'calls',
            $callNumber,
            self::numberWord($maximum),
        );
    }

    /**
     * Small, deliberately bounded word-form for the fabricated-note's
     * suggestion sentence ("if two calls here are legitimate..."), which
     * reads as prose rather than a code snippet — unlike the ->times(%d)
     * a few words later in the same sentence, which stays numeric because
     * it's something to paste. Falls back to the numeral past ten: nobody
     * configures ->times() with call counts large enough for spelled-out
     * numbers to matter, so this never needs to be a general-purpose
     * number-to-words converter.
     */
    private static function numberWord(int $count): string
    {
        static $words = [
            0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
            5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        ];

        return $words[$count] ?? (string) $count;
    }
}
