<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * The properties, constructor, and message for "an inOrder()-marked
 * expectation was called out of sequence" — shared, by both
 * OutOfOrderCallException and
 * Integrations\PHPUnit\PHPUnitOutOfOrderCallException. See
 * UnexpectedCallFields's docblock for why a trait, and why
 * TestDoubleException:: rather than self:: for the shared prose helper.
 */
trait OutOfOrderCallFields
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $alreadyOccurredMethod,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $method, $alreadyOccurredMethod, $fabricated));
    }

    public static function renderMessage(string $label, string $method, string $alreadyOccurredMethod, bool $fabricated): string
    {
        return sprintf(
            'Test double `%s` received `%s()` out of order: `%s()` already happened, and inOrder() '
            .'requires this to happen no later than that.%s',
            $label,
            $method,
            $alreadyOccurredMethod,
            TestDoubleException::fabricatedNote($fabricated),
        );
    }
}
