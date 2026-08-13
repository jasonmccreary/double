<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * One unmet expects() expectation, paired with every other observed call to
 * the same method.
 */
final class UnsatisfiedExpectation
{
    /**
     * @param  string[]  $otherObservedCalls  one pre-formatted argument list per call (the
     *                                        "(...)" part only — $method supplies the name).
     *                                        Pulled straight from the call log (every call to
     *                                        this method, matched or not), not a similarity guess.
     * @param  ?string  $argumentMismatch  pre-formatted "expected N arguments, got M" — an arity
     *                                     mismatch, the one case that can't be broken down
     *                                     argument by argument. Set only when $otherObservedCalls
     *                                     is exactly the one call this expectation didn't match.
     * @param  ?list<ArgumentComparison>  $argumentComparisons  one labeled entry per constrained
     *                                                          argument (plus any trailing Argument::remaining() ones),
     *                                                          set only when that one observed call had the right shape
     *                                                          (same argument count) — the one case where pairing
     *                                                          expected against actual, argument by argument, is a
     *                                                          fact rather than a guess. Mutually exclusive with
     *                                                          $argumentMismatch.
     */
    public function __construct(
        public readonly string $method,
        public readonly string $description,
        public readonly int $expectedMin,
        public readonly int $expectedMax,
        public readonly int $timesCalled,
        public readonly array $otherObservedCalls,
        public readonly ?string $argumentMismatch = null,
        public readonly ?array $argumentComparisons = null,
    ) {}
}
