<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\Pluralizer;
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
        if (count($expectations) === 1) {
            return self::renderSingle($label, $expectations[0], $fabricated);
        }

        return self::renderMultiple($label, $expectations, $fabricated);
    }

    private static function renderSingle(string $label, UnsatisfiedExpectation $expectation, bool $fabricated): string
    {
        $message = sprintf('Test double `%s` %s.', $label, $expectation->description);

        if ($expectation->otherObservedCalls !== []) {
            $message .= ' '.self::renderCorrelation($expectation);
        }

        return $message.TestDoubleException::fabricatedNote($fabricated);
    }

    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    private static function renderMultiple(string $label, array $expectations, bool $fabricated): string
    {
        $count = count($expectations);

        $message = sprintf(
            "%s not satisfied on test double `%s`:\n\n%s",
            Pluralizer::pluralize($count, 'expectation was', 'expectations were'),
            $label,
            implode("\n", array_map(
                static fn (UnsatisfiedExpectation $expectation): string => '    '.$expectation->description,
                $expectations,
            )),
        );

        return $message.TestDoubleException::fabricatedNote($fabricated);
    }

    private static function renderCorrelation(UnsatisfiedExpectation $expectation): string
    {
        $calls = implode(', ', array_map(
            static fn (string $call): string => sprintf('%s(%s)', $expectation->method, $call),
            $expectation->otherObservedCalls,
        ));

        return sprintf(
            '`%s` was called elsewhere in this test, just with different arguments: %s.',
            $expectation->method,
            $calls,
        );
    }
}
