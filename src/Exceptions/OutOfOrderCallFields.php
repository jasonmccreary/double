<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * The properties, constructor, and message for "an ordered()-marked
 * expectation was called out of sequence" — shared with
 * Integrations\PHPUnit\PHPUnitOutOfOrderCallException via a trait (see
 * UnexpectedCallFields).
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
            'Double `%s` received `%s()` out of order. Using `ordered`, this was expected to be '
            .'called before `%s()` was called.%s',
            $label,
            $method,
            $alreadyOccurredMethod,
            DoubleException::fabricatedNote($fabricated),
        );
    }
}
