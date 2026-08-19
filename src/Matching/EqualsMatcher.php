<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * For same-type values, === and == never disagree — using === for
 * everything but objects only removes cross-type surprises like '0' == 0,
 * never a legitimate same-type match. Arrays recurse so an object nested
 * inside one still matches by value instead of by identity.
 *
 * Like PHP's own === and ==, this doesn't support circular arrays (e.g.
 * `$a['self'] = &$a`) — comparing one recurses until the stack overflows.
 */
final class EqualsMatcher implements Matcher
{
    public function __construct(
        private readonly mixed $expected,
    ) {}

    /**
     * @internal Used only by MethodExpectation::describeMismatch() to reach
     * the raw value for a StringDiffer comparison — describe()/explainMismatch()
     * only ever expose it pre-formatted.
     */
    public function expected(): mixed
    {
        return $this->expected;
    }

    public function matches(mixed $actual): bool
    {
        return self::equals($this->expected, $actual);
    }

    private static function equals(mixed $expected, mixed $actual): bool
    {
        if (is_array($expected) && is_array($actual)) {
            if (array_keys($expected) !== array_keys($actual)) {
                return false;
            }

            foreach ($expected as $key => $value) {
                if (! self::equals($value, $actual[$key])) {
                    return false;
                }
            }

            return true;
        }

        return is_object($expected)
            ? $expected == $actual
            : $expected === $actual;
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
