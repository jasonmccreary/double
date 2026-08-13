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
        $message = sprintf('Double `%s` %s.', $label, $expectation->description);

        if ($expectation->argumentComparisons !== null) {
            // The one observed call had the right shape (same argument
            // count) — labeled, argument-by-argument detail replaces the
            // plain correlation paragraph entirely rather than sitting
            // alongside it.
            $message .= "\n\n".CallListFormatter::renderComparisonBlock(
                sprintf('The following similar call was made to `%s`:', $expectation->method),
                $expectation->argumentComparisons,
            );
        } elseif ($expectation->otherObservedCalls !== []) {
            $message .= "\n\n".CallListFormatter::renderCorrelationParagraph($expectation->method, $expectation->otherObservedCalls);

            // Only ever set alongside the correlation paragraph above (see
            // Double::verifyState()) — an arity mismatch, the one case that
            // can't be broken down argument by argument.
            if ($expectation->argumentMismatch !== null) {
                $message .= "\n".ucfirst($expectation->argumentMismatch);
            }
        }

        return DoubleException::appendFabricatedNote($message, $fabricated);
    }

    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    private static function renderMultiple(string $label, array $expectations, bool $fabricated): string
    {
        $count = count($expectations);

        $message = sprintf(
            "%s not satisfied on double `%s`:\n\n%s",
            Pluralizer::pluralize($count, 'expectation was', 'expectations were'),
            $label,
            implode("\n", array_map(
                static fn (UnsatisfiedExpectation $expectation): string => '    '.$expectation->description.self::renderInlineCorrelation($expectation),
                $expectations,
            )),
        );

        return DoubleException::appendFabricatedNote($message, $fabricated);
    }

    /**
     * Unlike renderSingle()'s full correlation paragraph, this stays inline
     * and only fires when otherObservedCalls is non-empty: that's proof the
     * method was actually reached during the test, so this entry is a real
     * mismatch rather than a downstream symptom of some other unsatisfied
     * expectation (e.g. the code under test aborted before reaching it).
     */
    private static function renderInlineCorrelation(UnsatisfiedExpectation $expectation): string
    {
        if ($expectation->otherObservedCalls === []) {
            return '';
        }

        return sprintf(
            ' (other calls: %s)',
            CallListFormatter::describe($expectation->method, $expectation->otherObservedCalls),
        );
    }
}
