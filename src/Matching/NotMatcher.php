<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * The negation itself, wrapping either a bare value (via EqualsMatcher) or
 * another Matcher — this constructor stays generic on purpose, since it's
 * used two different ways: directly, from Argument::not($literal)'s
 * one-argument form; and indirectly, from every NegatedArgument method
 * (Argument::not()->type(...), ->contains(...), etc.), each of which builds
 * the inner matcher and wraps it here. Argument::not($matcher) itself is
 * not a supported public spelling — see Argument::not()'s own docblock —
 * so this class is the shared mechanism behind both public entry points,
 * not a second one.
 *
 * describe()/explainMismatch() defer to the wrapped matcher's own
 * describe() (not(5), not(type(int))) rather than rendering an opaque
 * "<Not>" the way Mockery's own Not matcher does — the same "explain what
 * to do about it" standard every other diagnostic in this library is held
 * to. Mockery's Not also can't wrap another matcher at all, only ever
 * comparing by identity against a fixed value.
 */
final class NotMatcher implements Matcher
{
    private readonly Matcher $inner;

    public function __construct(mixed $expected)
    {
        $this->inner = $expected instanceof Matcher ? $expected : new EqualsMatcher($expected);
    }

    public function matches(mixed $actual): bool
    {
        return ! $this->inner->matches($actual);
    }

    public function describe(): string
    {
        return sprintf('not(%s)', $this->inner->describe());
    }

    public function explainMismatch(mixed $actual): ?string
    {
        if ($this->matches($actual)) {
            return null;
        }

        return sprintf(
            'expected anything but %s, got %s',
            $this->inner->describe(),
            ValueFormatter::describe($actual),
        );
    }
}
