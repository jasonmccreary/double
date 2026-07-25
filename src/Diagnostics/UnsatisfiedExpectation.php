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
     */
    public function __construct(
        public readonly string $method,
        public readonly string $description,
        public readonly int $expectedMin,
        public readonly int $expectedMax,
        public readonly int $timesCalled,
        public readonly array $otherObservedCalls,
    ) {}
}
