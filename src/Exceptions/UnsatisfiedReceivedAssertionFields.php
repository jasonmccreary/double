<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * The properties, constructor, and message for "a received() assertion's
 * chain didn't match what was actually recorded" — shared with
 * Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException via a
 * trait (see UnexpectedCallFields).
 */
trait UnsatisfiedReceivedAssertionFields
{
    public function __construct(
        public readonly string $label,
        public readonly string $description,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $description, $fabricated));
    }

    public static function renderMessage(string $label, string $description, bool $fabricated): string
    {
        return sprintf(
            'Test double `%s` %s.%s',
            $label,
            $description,
            TestDoubleException::fabricatedNote($fabricated),
        );
    }
}
