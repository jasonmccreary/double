<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\ArgumentComparison;
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
     * @param  ?string  $argumentMismatch  pre-formatted "expected N arguments, got M" — an arity
     *                                     mismatch, the one case that can't be broken down
     *                                     argument by argument. Set only when $otherObservedCalls
     *                                     is exactly the one recorded call this assertion didn't
     *                                     match and the failure is that too few calls matched
     *                                     (never()'s violation — a call matching fine when it
     *                                     shouldn't have happened at all — has no argument
     *                                     mismatch to report).
     * @param  ?list<ArgumentComparison>  $argumentComparisons  one labeled entry per constrained
     *                                                          argument, set only under that same one-call, too-few-matches
     *                                                          condition, when the shapes actually differ argument by
     *                                                          argument rather than in count.
     */
    public function __construct(
        public readonly string $label,
        public readonly string $description,
        public readonly bool $fabricated = false,
        public readonly string $method = '',
        public readonly array $otherObservedCalls = [],
        public readonly ?string $argumentMismatch = null,
        public readonly ?array $argumentComparisons = null,
    ) {
        parent::__construct(self::renderMessage($label, $description, $fabricated, $method, $otherObservedCalls, $argumentMismatch, $argumentComparisons));
    }

    /**
     * @param  list<string>  $otherObservedCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function renderMessage(
        string $label,
        string $description,
        bool $fabricated,
        string $method = '',
        array $otherObservedCalls = [],
        ?string $argumentMismatch = null,
        ?array $argumentComparisons = null,
    ): string {
        $message = sprintf('Double `%s` %s.', $label, $description);

        if ($argumentComparisons !== null) {
            $message .= "\n\n".CallListFormatter::renderComparisonBlock(
                sprintf('The following similar call was made to `%s`:', $method),
                $argumentComparisons,
            );
        } elseif ($otherObservedCalls !== []) {
            $message .= "\n\n".CallListFormatter::renderCorrelationParagraph($method, $otherObservedCalls);

            // Only ever set alongside the correlation paragraph above — an
            // arity mismatch, the one case that can't be broken down
            // argument by argument.
            if ($argumentMismatch !== null) {
                $message .= "\n".ucfirst($argumentMismatch);
            }
        }

        return DoubleException::appendFabricatedNote($message, $fabricated);
    }
}
