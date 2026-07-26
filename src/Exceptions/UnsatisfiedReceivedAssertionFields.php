<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\CallListFormatter;

/**
 * The properties, constructor, and message for "a received() assertion's
 * chain didn't match what was actually recorded" — shared with
 * Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException via a
 * trait (see UnexpectedCallFields).
 */
trait UnsatisfiedReceivedAssertionFields
{
    /**
     * @param  list<string>  $otherObservedCalls  every other recorded call to $method (matched
     *                                            or not), pulled straight from the call log —
     *                                            same plain-fact rule UnsatisfiedExpectation and
     *                                            UnexpectedCallFields use
     */
    public function __construct(
        public readonly string $label,
        public readonly string $description,
        public readonly bool $fabricated = false,
        public readonly string $method = '',
        public readonly array $otherObservedCalls = [],
    ) {
        parent::__construct(self::renderMessage($label, $description, $fabricated, $method, $otherObservedCalls));
    }

    /**
     * @param  list<string>  $otherObservedCalls
     */
    public static function renderMessage(
        string $label,
        string $description,
        bool $fabricated,
        string $method = '',
        array $otherObservedCalls = [],
    ): string {
        $message = sprintf('Test double `%s` %s.', $label, $description);

        if ($otherObservedCalls !== []) {
            $message .= "\n\n".CallListFormatter::renderCorrelationParagraph($method, $otherObservedCalls);
        }

        return TestDoubleException::appendFabricatedNote($message, $fabricated);
    }
}
