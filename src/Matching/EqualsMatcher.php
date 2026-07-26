<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * For same-type values, === and == never disagree — using === for
 * everything but objects only removes cross-type surprises like '0' == 0,
 * never a legitimate same-type match.
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
