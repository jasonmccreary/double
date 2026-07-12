<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

/**
 * @internal
 *
 * Backs $double->received($method) — a spy-style assertion over calls
 * already recorded in DoubleState, as opposed to expects()/allows(), which
 * configure behavior for calls that haven't happened yet and get checked in
 * bulk at verify() time.
 *
 * Reuses MethodExpectation wholesale for the argument-matching and
 * count-bounds machinery (with()/times()/atLeastOnce()/never(), plus
 * describe()'s "expected X, called Y" rendering) rather than
 * reimplementing it — a received() assertion is structurally the same
 * bounded-count-plus-argument-matcher shape as an expectation, just checked
 * against the past instead of the future. This internal MethodExpectation
 * is never registered on DoubleState: it must never participate in live
 * call matching (ProxyBehavior) or verify()'s unmet-expectations list, only
 * in this class's own after-the-fact check.
 *
 * Checked exactly once, from __destruct(), when the fluent chain's last
 * reference is discarded (an unassigned statement like
 * `$double->received('save')->with($book)->never();` is destroyed by PHP's
 * refcounting at the end of that statement, well within the normal flow a
 * test framework's try/catch already wraps around the test method). This
 * is what makes composing constraints possible — e.g. with() then never()
 * ("assert this was never called with these specific args") — since no
 * single fluent method can know whether another one is coming after it,
 * only the final accumulated state at destruction time.
 *
 * Known, deliberate trade-off: relying on __destruct() timing means a
 * chain assigned to a variable that outlives the statement (e.g. held in a
 * loop or passed around) won't assert until that variable actually goes
 * out of scope — later than the call site suggests, though still normally
 * within the same test. This was chosen over adding a required terminal
 * verb (composable but easy to forget, silently never asserting) or
 * restricting received() to non-composable single-constraint chains (safe
 * but couldn't express with()+never() together at all).
 */
final class ReceivedAssertion
{
    private readonly MethodExpectation $expectation;

    public function __construct(
        private readonly DoubleState $state,
        private readonly string $method,
    ) {
        $this->expectation = (new MethodExpectation($method, required: false))->atLeastOnce();
    }

    public function with(mixed ...$arguments): static
    {
        $this->expectation->with(...$arguments);

        return $this;
    }

    public function times(?int $count = null, ?int $maximum = null, ?int $minimum = null): static
    {
        $this->expectation->times($count, $maximum, $minimum);

        return $this;
    }

    public function atLeastOnce(): static
    {
        $this->expectation->atLeastOnce();

        return $this;
    }

    public function never(): static
    {
        $this->expectation->never();

        return $this;
    }

    public function __destruct()
    {
        foreach ($this->state->callsFor($this->method) as $call) {
            if ($this->expectation->matchesArguments($call)) {
                $this->expectation->recordMatch($call);
            }
        }

        if ($this->expectation->isSatisfied() && ! $this->expectation->exceedsMaximum()) {
            return;
        }

        throw ExceptionFactory::unsatisfiedReceivedAssertion(
            $this->state->label(),
            $this->expectation->describe(),
            $this->state->isFabricated(),
        );
    }
}
