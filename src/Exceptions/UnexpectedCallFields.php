<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\ArgumentComparison;
use JMac\Testing\Diagnostics\CallListFormatter;

/**
 * The properties, constructor, and message for "Strict mode got an
 * unexpected call" — shared with
 * Integrations\PHPUnit\PHPUnitUnexpectedCallException via a trait, since two
 * classes with different fixed parents (DoubleException vs.
 * AssertionFailedError) can't share code any other way. Each class still
 * gets its own real, independent instance.
 */
trait UnexpectedCallFields
{
    /**
     * @param  list<string>  $otherObservedCalls  every other call already observed for this
     *                                            method (matched or not, same plain-fact rule
     *                                            UnsatisfiedExpectation::$otherObservedCalls
     *                                            uses), excluding this failing call itself
     * @param  ?list<ArgumentComparison>  $argumentComparisons  one labeled entry per constrained
     *                                                          argument, set only when $otherObservedCalls is exactly
     *                                                          the one other call already observed for this method
     *                                                          and it has the right shape (same argument count) as
     *                                                          this failing one — the one case where pairing this
     *                                                          call against that other one, argument by argument, is
     *                                                          a fact rather than a guess. Same rule
     *                                                          UnsatisfiedExpectation::$argumentComparisons uses.
     */
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
        public readonly array $otherObservedCalls = [],
        public readonly ?array $argumentComparisons = null,
    ) {
        parent::__construct(self::renderMessage($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls, $argumentComparisons));
    }

    /**
     * @param  list<string>  $otherObservedCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function renderMessage(
        string $label,
        string $method,
        string $argumentsDescription,
        bool $fabricated,
        array $otherObservedCalls = [],
        ?array $argumentComparisons = null,
    ): string {
        $message = sprintf(
            'Double `%s` received an unexpected call to `%s(%s)`. Strict mode requires every call to be configured.',
            $label,
            $method,
            $argumentsDescription,
        );

        // A configuration suggestion, or even the plain correlation fact, only
        // makes sense when there's nothing better to offer. Once there's
        // exactly one other call this one is a checked near miss of,
        // pinpointing exactly how it differs is strictly more useful than
        // either — a guess on three axes (variable name, return value,
        // allows() vs. expects()) or a bare list of unrelated past calls.
        if ($argumentComparisons !== null) {
            $message .= "\n\n".CallListFormatter::renderComparisonBlock(
                sprintf('The following similar call was made to `%s`:', $method),
                $argumentComparisons,
            );
        } elseif ($otherObservedCalls !== []) {
            $message .= "\n\n".CallListFormatter::renderCorrelationParagraph($method, $otherObservedCalls);
        } else {
            $message .= ' '.self::renderSuggestion($label, $method);
        }

        return DoubleException::appendFabricatedNote($message, $fabricated);
    }

    private static function renderSuggestion(string $label, string $method): string
    {
        return sprintf(
            'For example: `$%s->allows(\'%s\')->returns(...)`.',
            // DoubleException::, not self:: — PHPUnitUnexpectedCallException
            // doesn't extend DoubleException, so it can't inherit this helper.
            DoubleException::suggestedVariableName($label),
            $method,
        );
    }
}
