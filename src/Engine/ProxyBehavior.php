<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\UnexpectedCallException;
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

        $expectation->recordMatch();

        if ($expectation->exceedsMaximum()) {
            throw ExpectationCallLimitExceededException::forExpectation(
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

    private static function handleUnmatchedCall(DoubleState $state, string $method, array $arguments, object $double): mixed
    {
        return match ($state->mode()) {
            Mode::Strict => throw UnexpectedCallException::forCall(
                $state->label(),
                $method,
                ArgumentFormatter::describe($arguments),
                $state->isFabricated(),
            ),
            Mode::Loose => SafeDefaultResolver::resolveForMethod($state, $method, $double),
            Mode::Passthru => $state->passthruTarget()->{$method}(...$arguments),
        };
    }
}
