<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * One unmet expects() expectation, paired with every other call actually
 * observed for the same method — see ARCHITECTURE.md, "Correlating
 * unsatisfied expectations with actual observed calls." $otherObservedCalls
 * is a plain fact pulled from DoubleState's call log (every call to this
 * method, matched or not), never a similarity guess — that distinction is
 * why this reads differently from the closest-candidate diffing on the
 * unexpected-call path.
 */
final class UnsatisfiedExpectation
{
    /**
     * @param  string[]  $otherObservedCalls  one pre-formatted argument list per call (the
     *                                        "(...)" part only — $method supplies the name)
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
