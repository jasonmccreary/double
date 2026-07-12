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
 * Checked lazily — never eagerly on with()/times()/atLeastOnce()/never()
 * themselves — because no single fluent method can know whether another one
 * is coming after it; only the final accumulated state, once the chain is
 * done being composed, means anything. That's what makes with() then
 * never() possible ("assert this was never called with these specific
 * args") as one composed check, the way Mockery needs a second verb
 * (shouldNotHaveReceived()) to express instead.
 *
 * Two paths converge on the same check() (idempotent via $checked, so
 * whichever path runs first wins and the other becomes a no-op):
 *
 * - __destruct(), when the fluent chain's last reference is discarded — an
 *   unassigned statement like `$double->received('save')->with($book)->never();`
 *   is destroyed by PHP's refcounting at the end of that statement. This is
 *   the only path for any test runner besides PHPUnit, and for PHPUnit
 *   users not using Integrations\PHPUnit\VerifiesDoubles — see that
 *   trait's docblock; there is deliberately no framework-agnostic
 *   equivalent of it, so this is the fallback that keeps received()
 *   working with zero setup everywhere else.
 * - TestDouble::$pendingReceived, when VerifiesDoubles is active: registered
 *   at construction (see TestDouble::received()), same strong-reference
 *   reasoning as TestDouble::$pending for expects() (see that field's
 *   docblock) — a chain assigned to a variable that outlives its statement
 *   would otherwise not assert until that variable happens to go out of
 *   scope, which is a different, harder-to-predict mechanism than
 *   verify()'s "every expects() gets checked at the same #[After] hook."
 *   Registering it there means both verbs are checked from the exact same
 *   place, at the exact same time, once VerifiesDoubles is in play — the
 *   destructor becomes purely the non-PHPUnit fallback, not a second
 *   competing mechanism for PHPUnit users.
 */
final class ReceivedAssertion
{
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

    public function __destruct()
    {
        $this->check();
    }

    /**
     * @internal used only by TestDouble::verifyAll() — see this class's own
     * docblock for why that path and __destruct() both lead here, and why
     * $checked makes running it twice safe.
     */
    public function check(): void
    {
        if ($this->checked) {
            return;
        }

        $this->checked = true;

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
