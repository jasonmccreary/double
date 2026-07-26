<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Diagnostics\ArgumentFormatter;

/**
 * @internal
 *
 * Backs $double->received($method) — a spy-style assertion over calls
 * already recorded in DoubleState, as opposed to expects()/allows(), which
 * configure behavior for calls that haven't happened yet.
 */
final class ReceivedAssertion
{
    // Reuses MethodExpectation wholesale for the argument-matching and
    // count-bounds machinery rather than reimplementing it — a received()
    // assertion is the same bounded-count-plus-argument-matcher shape as an
    // expectation, just checked against the past instead of the future.
    // Never registered on DoubleState: it must never participate in live
    // call matching (ProxyBehavior) or verify()'s unmet-expectations list.
    private readonly MethodExpectation $expectation;

    private bool $checked = false;

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

    // Checked lazily, not eagerly from with()/times()/atLeastOnce()/never()
    // themselves — only the final accumulated state, once the fluent chain
    // is done, means anything. That's what makes `->with($book)->never()`
    // work as one composed check. Fires when an unassigned statement's last
    // reference is discarded by refcounting — the fallback path for any
    // test runner besides PHPUnit's VerifiesDoubles trait.
    public function __destruct()
    {
        $this->check();
    }

    /**
     * @internal Also called directly by TestDouble::verifyAll(), when
     * VerifiesDoubles registers this assertion up front (see
     * TestDouble::received()). $checked makes running it twice safe —
     * whichever path fires first wins and the other becomes a no-op.
     */
    public function check(): void
    {
        if ($this->checked) {
            return;
        }

        $this->checked = true;

        $calls = $this->state->callsFor($this->method);

        foreach ($calls as $call) {
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
            $this->method,
            array_map(ArgumentFormatter::describe(...), $calls),
        );
    }
}
