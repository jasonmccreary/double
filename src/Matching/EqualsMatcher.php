<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * The default a bare literal is wrapped in at the with() boundary (see
 * ARCHITECTURE.md, "Matcher"). Loose value equality (==), matching the
 * placeholder comparison MethodExpectation used before this module
 * existed.
 */
final class EqualsMatcher implements Matcher
{
    public function __construct(
        private readonly mixed $expected,
    ) {}

    public function matches(mixed $actual): bool
    {
        return $this->expected == $actual;
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
