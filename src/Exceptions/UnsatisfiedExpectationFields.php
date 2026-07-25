<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\CallListFormatter;
use JMac\Testing\Diagnostics\Pluralizer;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;

/**
 * The properties, constructor, and message for "one or more expects()
 * expectations were never satisfied" — shared with
 * Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException via a trait
 * (see UnexpectedCallFields).
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
            $message .= "\n\n".CallListFormatter::renderCorrelationParagraph($expectation->method, $expectation->otherObservedCalls);
        }

        return TestDoubleException::appendFabricatedNote($message, $fabricated);
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

        return TestDoubleException::appendFabricatedNote($message, $fabricated);
    }
}
