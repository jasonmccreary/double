<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\UnconfiguredReturnException;
use JMac\Testing\Exceptions\UnexpectedCallException;

/**
 * @internal
 *
 * The call-interception logic every generated double's overridden methods
 * funnel through. Stateless — everything it needs comes from the
 * DoubleState passed in.
 *
 * Matching rule: last-registered expectation whose arguments match wins
 * (see ARCHITECTURE.md, "Expectation matching order"). No exhaustion-based
 * fallthrough to an earlier expectation once one is selected.
 *
 * Mode handling: M1 only implements Strict (see DoubleState::mode() for
 * the M1 stand-in default). The Loose/Passthru branches below are
 * unreachable today — DoubleControlMethods::passthru() throws before mode
 * could ever become Passthru, and there is no way to request Loose
 * explicitly — but the structure is here so M4 only has to add behavior,
 * not restructure this method.
 */
final class ProxyBehavior
{
    public static function intercept(DoubleState $state, string $method, array $arguments): mixed
    {
        $state->recordCall($method, $arguments);

        $expectation = self::findMatch($state, $method, $arguments);

        if ($expectation === null) {
            return self::handleUnmatchedCall($state, $method, $arguments);
        }

        $expectation->recordMatch();

        if ($expectation->exceedsMaximum()) {
            throw ExpectationCallLimitExceededException::forExpectation(
                $state->label(),
                $method,
                ArgumentFormatter::describe($arguments),
                $expectation->maximumCalls(),
                $expectation->timesMatched(),
            );
        }

        if (! $expectation->hasReturnConfigured()) {
            throw UnconfiguredReturnException::forCall($state->label(), $method);
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

    private static function handleUnmatchedCall(DoubleState $state, string $method, array $arguments): mixed
    {
        return match ($state->mode()) {
            Mode::Strict => throw UnexpectedCallException::forCall(
                $state->label(),
                $method,
                ArgumentFormatter::describe($arguments),
            ),
            Mode::Loose, Mode::Passthru => throw new \LogicException(
                'Unreachable in M1: Loose/Passthru fallback behavior ships in M4.',
            ),
        };
    }
}
