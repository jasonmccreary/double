<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\ArgumentComparison;
use JMac\Testing\Diagnostics\CallListFormatter;
use JMac\Testing\Diagnostics\Pluralizer;

/**
 * The properties, constructor, and message for "a call was made to a method
 * that has expects() configured, but it doesn't match any of that method's
 * configured expectations" — shared with
 * Integrations\PHPUnit\PHPUnitExpectationCallMismatchException via a trait
 * (see UnexpectedCallFields).
 *
 * Distinct from UnexpectedCallException: that one is Strict mode's blanket
 * policy ("every call must be configured"), thrown regardless of what's been
 * configured for the method in question. This one fires only because
 * expects() was used on this specific method — the double as a whole may
 * still be in Loose mode.
 */
trait ExpectationCallMismatchFields
{
    /**
     * @param  list<string>  $configuredCalls  every candidate expectation registered for this
     *                                         method (expects() or allows()), each rendered as
     *                                         just its argument pattern — the "(...)" part only,
     *                                         same convention CallListFormatter::describe() uses.
     *                                         Always populated when there's at least one
     *                                         candidate — same "always set, precedence handled by
     *                                         the renderer" convention as UnexpectedCallFields's
     *                                         own $otherObservedCalls.
     * @param  ?list<ArgumentComparison>  $argumentComparisons  one labeled entry per constrained
     *                                                          argument, set only when exactly one
     *                                                          expectation is registered for this
     *                                                          method *and* it has the right shape
     *                                                          (same argument count) as this call —
     *                                                          the one case where pairing this call
     *                                                          against it, argument by argument, is
     *                                                          a fact rather than a guess between
     *                                                          several candidates. An arity mismatch
     *                                                          against a single candidate falls back
     *                                                          to the plain $configuredCalls list
     *                                                          below, same as UnexpectedCallFields.
     * @param  bool  $passthru  whether the double this call was made on is in passthru mode —
     *                          this exception fires the same way regardless of mode, but only
     *                          passthru's "real behavior unless overridden" premise makes
     *                          rejecting the call instead of delegating worth calling out
     *                          explicitly (see DoubleException::passthruNote()).
     */
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
        public readonly array $configuredCalls = [],
        public readonly ?array $argumentComparisons = null,
        public readonly bool $passthru = false,
    ) {
        parent::__construct(self::renderMessage($label, $method, $argumentsDescription, $fabricated, $configuredCalls, $argumentComparisons, $passthru));
    }

    /**
     * @param  list<string>  $configuredCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function renderMessage(
        string $label,
        string $method,
        string $argumentsDescription,
        bool $fabricated,
        array $configuredCalls = [],
        ?array $argumentComparisons = null,
        bool $passthru = false,
    ): string {
        $message = sprintf(
            'Double `%s` received a call to `%s(%s)` that doesn\'t match any of its configured expectations. `expects()` requires every call to match one exactly.',
            $label,
            $method,
            $argumentsDescription,
        );

        // Exactly one configured expectation is the one case where pinpointing
        // how this call differs from it, argument by argument, is a fact
        // rather than a guess between several candidates — same rule
        // UnexpectedCallFields and UnsatisfiedExpectationFields both use.
        if ($argumentComparisons !== null) {
            $message .= "\n\n".CallListFormatter::renderComparisonBlock(
                sprintf('The following similar call was made to `%s`:', $method),
                $argumentComparisons,
            );
        } elseif ($configuredCalls !== []) {
            $message .= sprintf(
                "\n\nThis double has %s configured for `%s`, but none match this call: %s",
                Pluralizer::pluralize(count($configuredCalls), 'expectation', 'expectations'),
                $method,
                CallListFormatter::describe($method, $configuredCalls),
            );
        }

        $message = DoubleException::appendPassthruNote($message, $passthru);

        return DoubleException::appendFabricatedNote($message, $fabricated);
    }
}
