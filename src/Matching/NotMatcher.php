<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * The negation itself, wrapping either a bare value (via EqualsMatcher) or
 * another Matcher — used both directly, from Argument::not($literal)'s
 * one-argument form, and indirectly, from every NegatedArgument method.
 *
 * describe()/explainMismatch() defer to the wrapped matcher's own describe()
 * (not(5), not(type(int))) rather than rendering an opaque "<Not>" that would
 * lose what was actually being negated.
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
