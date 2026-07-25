<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Diagnostics\ArgumentFormatter;
use JMac\Testing\TestDouble;

/**
 * @internal
 *
 * The call-interception logic every generated double's overridden methods
 * funnel through. Takes the double instance itself (not just its
 * DoubleState) because resolving a self/static safe-default return needs
 * the actual object to return (see SafeDefaultResolver).
 *
 * Matching rule: last-registered expectation whose arguments match wins
 * (see ARCHITECTURE.md, "Expectation matching order"). No exhaustion-based
 * fallthrough to an earlier expectation once one is selected.
 *
 * A matched expectation with no configured return, and Loose mode's
 * unmatched-call fallback, both resolve through the same
 * SafeDefaultResolver::resolveForMethod() — see ARCHITECTURE.md's "Sensible
 * defaults": "there is only one safe-default-by-return-type resolver in the
 * codebase, used at both call sites."
 */
final class ProxyBehavior
{
    public static function intercept(object $double, string $method, array $arguments): mixed
    {
        $state = TestDouble::stateFor($double);

        $state->recordCall($method, $arguments);

        $expectation = self::findMatch($state, $method, $arguments);

        if ($expectation === null) {
            return self::handleUnmatchedCall($state, $method, $arguments, $double);
        }

        $expectation->recordMatch($arguments);

        self::enforceOrder($state, $expectation);

        if ($expectation->exceedsMaximum()) {
            throw ExceptionFactory::expectationCallLimitExceeded(
                $state->label(),
                $method,
                ArgumentFormatter::describe($arguments),
                $expectation->maximumCalls(),
                $expectation->timesMatched(),
                $state->isFabricated(),
            );
        }

        if (! $expectation->hasReturnConfigured()) {
            return SafeDefaultResolver::resolveForMethod($state, $method, $double);
        }

        return $expectation->resolveReturn($arguments);
    }

    private static function findMatch(DoubleState $state, string $method, array $arguments): ?MethodExpectation
    {
        $candidates = $state->expectationsFor($method);

        for ($i = count($candidates) - 1; $i >= 0; $i--) {
            if ($candidates[$i]->matchesArguments($arguments)) {
                return $candidates[$i];
            }
        }

        return null;
    }

    /**
     * Orthogonal to findMatch() above, not part of it — see
     * ARCHITECTURE.md, "Call-order enforcement". Only ever runs against
     * whichever expectation findMatch() already selected; never changes
     * that selection. A no-op unless $expectation itself was marked
     * inOrder(). Rejects only regression (a slot behind the furthest one
     * already reached) — reaching a later slot without every slot in
     * between having fired is allowed, mirroring Mockery's own
     * validateOrder(); a skipped required step still surfaces separately,
     * via the ordinary unmet-expectation check at verify() time.
     */
    private static function enforceOrder(DoubleState $state, MethodExpectation $expectation): void
    {
        if (! $expectation->isOrdered()) {
            return;
        }

        $ordered = $state->orderedExpectations();
        $slot = array_search($expectation, $ordered, true);

        if ($slot < $state->orderCursor()) {
            throw ExceptionFactory::outOfOrderCall(
                $state->label(),
                $expectation->method(),
                $ordered[$state->orderCursor()]->method(),
                $state->isFabricated(),
            );
        }

        $state->advanceOrderCursor($slot);
    }

    private static function handleUnmatchedCall(DoubleState $state, string $method, array $arguments, object $double): mixed
    {
        return match ($state->mode()) {
            // callsFor($method) already includes this very call — recordCall() above ran
            // before findMatch() — so the last entry is dropped to leave only calls that
            // happened before this one.
            Mode::Strict => throw ExceptionFactory::unexpectedCall(
                $state->label(),
                $method,
                ArgumentFormatter::describe($arguments),
                $state->isFabricated(),
                array_map(ArgumentFormatter::describe(...), array_slice($state->callsFor($method), 0, -1)),
            ),
            Mode::Loose => SafeDefaultResolver::resolveForMethod($state, $method, $double),
            Mode::Passthru => $state->passthruTarget()->{$method}(...$arguments),
        };
    }
}
