<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * The default a bare literal is wrapped in at the with() boundary (see
 * ARCHITECTURE.md, "Strict-by-default scalar/array matching, loose objects,
 * explicit object identity"). Objects still compare with == ("an equivalent
 * value was passed" is the more common assertion for them); everything else
 * (scalars, arrays) compares with === instead, since for same-type values
 * === and == never disagree — only cross-type surprises like '0' == 0 are
 * removed, never a legitimate same-type match. Object identity has its own
 * matcher, Argument::same(), rather than changing what a bare literal
 * means for objects.
 */
final class EqualsMatcher implements Matcher
{
    public function __construct(
        private readonly mixed $expected,
    ) {}

    public function matches(mixed $actual): bool
    {
        return is_object($this->expected)
            ? $this->expected == $actual
            : $this->expected === $actual;
    }

    public function describe(): string
    {
        return ValueFormatter::describe($this->expected);
    }

    public function explainMismatch(mixed $actual): ?string
    {
        if ($this->matches($actual)) {
            return null;
        }

        return sprintf(
            'expected %s, got %s',
            ValueFormatter::describe($this->expected),
            ValueFormatter::describe($actual),
        );
    }
}
