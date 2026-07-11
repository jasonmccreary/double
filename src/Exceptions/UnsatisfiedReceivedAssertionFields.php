<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * The properties, constructor, and message for "a received() assertion's
 * chain didn't match what was actually recorded" — shared, by both
 * UnsatisfiedReceivedAssertionException and
 * Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException. See
 * UnexpectedCallFields's docblock for why a trait, and why
 * TestDoubleException:: rather than self:: for the shared prose helper.
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
            'Test double "%s" received() assertion failed: %s.%s',
            $label,
            $description,
            TestDoubleException::fabricatedNote($fabricated),
        );
    }
}
