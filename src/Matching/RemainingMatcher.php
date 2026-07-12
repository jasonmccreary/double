<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Argument::remaining() — a trailing marker for with(), not a per-position
 * matcher like any()/type()/satisfies(): it means "every argument from this
 * position to the end of the call, however many there are, is
 * unconstrained" rather than "this one position matches anything." Kept as
 * its own matcher rather than a second sense of any(), since any()'s
 * meaning (matches exactly one position) would otherwise depend on where it
 * appeared — see MethodExpectation::matchesArguments(), which is what
 * actually special-cases this as the list's trailing element; this class
 * itself only needs to satisfy the Matcher contract so with() can accept it
 * exactly like every other matcher, with no special-casing there.
 *
 * MethodExpectation::with() rejects this anywhere but the last position —
 * "match this exact value, then anything for the rest" only means
 * something as a trailing marker.
 */
final class RemainingMatcher implements Matcher
{
    public function matches(mixed $actual): bool
    {
        return true;
    }

    public function describe(): string
    {
        return '...';
    }

    public function explainMismatch(mixed $actual): ?string
    {
        return null;
    }
}
