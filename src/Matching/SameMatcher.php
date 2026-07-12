<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * Argument::same($expected) — backed by ===, for the one case
 * EqualsMatcher deliberately still defaults to == on: object identity ("this
 * exact instance," not "an equivalent value"). Named after PHPUnit's own
 * assertSame() (the more directly relevant prior art, since this library
 * integrates with PHPUnit directly), not php.net's operator-name table —
 * see ARCHITECTURE.md, "Strict-by-default scalar/array matching, loose
 * objects, explicit object identity."
 */
final class SameMatcher implements Matcher
{
    public function __construct(
        private readonly mixed $expected,
    ) {}

    public function matches(mixed $actual): bool
    {
        return $this->expected === $actual;
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
            'expected the same %s, got %s',
            ValueFormatter::describe($this->expected),
            ValueFormatter::describe($actual),
        );
    }
}
