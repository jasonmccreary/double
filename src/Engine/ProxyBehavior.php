<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Diagnostics\ArgumentFormatter;
use JMac\Testing\Double;

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
        $state = Double::stateFor($double);

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
                self::countOtherMatchingExpectations($state, $method, $arguments, $expectation),
            );
        }

        if (! $expectation->hasReturnConfigured()) {
            // Same safe-default-by-return-type resolver as Loose mode's
            // unmatched-call fallback below — there's only one in the codebase.
            return SafeDefaultResolver::resolveForMethod($state, $method, $double);
        }

        return $expectation->resolveReturn($arguments);
    }

    // Last-registered expectation whose arguments match *and still has room
    // under its own times()/maximumCalls() budget* wins, falling through to
    // less-recently-registered candidates once one is exhausted. Only once
    // every matching candidate is exhausted does the most-recently-registered
    // one get reused, purely so the "exceeds maximum" error still has a
    // concrete expectation to report against.
    private static function findMatch(DoubleState $state, string $method, array $arguments): ?MethodExpectation
    {
        $candidates = $state->expectationsFor($method);
        $fallback = null;

        for ($i = count($candidates) - 1; $i >= 0; $i--) {
            if (! $candidates[$i]->matchesArguments($arguments)) {
                continue;
            }

            if ($candidates[$i]->timesMatched() < $candidates[$i]->maximumCalls()) {
                return $candidates[$i];
            }

            $fallback ??= $candidates[$i];
        }

        return $fallback;
    }

    /**
     * How many *other* expectations registered for $method also match this
     * call's arguments — walking the exact same candidate pool findMatch()
     * already checked. Surfaced in expectationCallLimitExceeded()'s message
     * so failure mode 1a (a starved expectation, not the one actually
     * reported, is the real problem) is self-diagnosing instead of
     * requiring a source-level read of registration order to spot.
     */
    private static function countOtherMatchingExpectations(
        DoubleState $state,
        string $method,
        array $arguments,
        MethodExpectation $selected,
    ): int {
        $count = 0;

        foreach ($state->expectationsFor($method) as $candidate) {
            if ($candidate !== $selected && $candidate->matchesArguments($arguments)) {
                $count++;
            }
        }

        return $count;
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
