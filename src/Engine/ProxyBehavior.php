<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Diagnostics\ArgumentFormatter;
use JMac\Testing\TestDouble;

/**
 * @internal
 *
 * The call-interception logic every generated double's overridden methods
 * funnel through.
 */
final class ProxyBehavior
{
    // Takes the double instance itself, not just its DoubleState, because
    // resolving a self/static safe-default return needs the actual object
    // to return (see SafeDefaultResolver).
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
            // Same safe-default-by-return-type resolver as Loose mode's
            // unmatched-call fallback below — there's only one in the codebase.
            return SafeDefaultResolver::resolveForMethod($state, $method, $double);
        }

        return $expectation->resolveReturn($arguments);
    }

    // Last-registered expectation whose arguments match wins — no
    // exhaustion-based fallthrough to an earlier expectation once one is found.
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
     * Enforces inOrder() sequencing for whichever expectation findMatch()
     * already selected; never changes that selection.
     */
    private static function enforceOrder(DoubleState $state, MethodExpectation $expectation): void
    {
        if (! $expectation->isOrdered()) {
            return;
        }

        $ordered = $state->orderedExpectations();
        $slot = array_search($expectation, $ordered, true);

        // Reject only regression to before the furthest slot already reached —
        // reaching a later slot without every slot in between firing is allowed.
        // A skipped required step still surfaces via the ordinary
        // unmet-expectation check at verify() time.
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
