<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;

/**
 * The properties, constructor, and message for "one or more expects()
 * expectations were never satisfied" — shared, by both
 * UnsatisfiedExpectationException and
 * Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException. See
 * UnexpectedCallFields's docblock for why a trait, and why
 * TestDoubleException:: rather than self:: for the shared prose helper.
 */
trait UnsatisfiedExpectationFields
{
    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public function __construct(
        public readonly string $label,
        public readonly array $expectations,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $expectations, $fabricated));
    }

    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public static function renderMessage(string $label, array $expectations, bool $fabricated): string
    {
        $blocks = array_map(self::renderOne(...), $expectations);
        $count = count($expectations);

        $message = sprintf(
            "%s %s not satisfied on test double \"%s\":\n\n%s",
            Pluralizer::pluralize($count, 'expectation', 'expectations'),
            $count === 1 ? 'was' : 'were',
            $label,
            implode("\n\n", $blocks),
        );

        if ($fabricated) {
            $message .= "\n\n".trim(TestDoubleException::fabricatedNote(true));
        }

        return $message;
    }

    private static function renderOne(UnsatisfiedExpectation $expectation): string
    {
        $lines = ['    '.$expectation->description];

        if ($expectation->otherObservedCalls !== []) {
            $lines[] = '';
            $lines[] = sprintf(
                '    "%s" was called with different arguments elsewhere in this test:',
                $expectation->method,
            );
            $lines[] = '';

            foreach ($expectation->otherObservedCalls as $call) {
                $lines[] = sprintf('        %s(%s)', $expectation->method, $call);
            }
        }

        return implode("\n", $lines);
    }
}
