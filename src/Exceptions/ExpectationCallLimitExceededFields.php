<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\Pluralizer;

/**
 * The properties, constructor, and message for "a call exceeded its
 * expectation's configured maximum" — shared with
 * Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException via a
 * trait (see UnexpectedCallFields).
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
        public readonly int $otherMatchingExpectations = 0,
    ) {
        parent::__construct(self::renderMessage(
            $label,
            $method,
            $argumentsDescription,
            $maximum,
            $callNumber,
            $fabricated,
            $otherMatchingExpectations,
        ));
    }

    public static function renderMessage(
        string $label,
        string $method,
        string $argumentsDescription,
        int $maximum,
        int $callNumber,
        bool $fabricated,
        int $otherMatchingExpectations = 0,
    ): string {
        $message = sprintf(
            'Test double `%s` received %s to `%s(%s)`, but your expectation only allowed %s.',
            $label,
            Pluralizer::pluralize($callNumber, 'call', 'calls'),
            $method,
            $argumentsDescription,
            Pluralizer::pluralize($maximum, 'call', 'calls'),
        );

        // Only meaningful once there's a real registration-order tangle to point
        // at (failure mode 1a: a starved expectation, not the one actually
        // reported, was the real problem) — otherwise this expectation is simply
        // the only one, and the message above already says everything there is
        // to say.
        if ($otherMatchingExpectations > 0) {
            $message .= $otherMatchingExpectations === 1
                ? sprintf(
                    ' Note: 1 other expectation for `%s` also matches this call\'s arguments but was not selected — check registration order.',
                    $method,
                )
                : sprintf(
                    ' Note: %d other expectations for `%s` also match this call\'s arguments but were not selected — check registration order.',
                    $otherMatchingExpectations,
                    $method,
                );
        }

        return $message.TestDoubleException::fabricatedNote($fabricated);
    }
}
