<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Argument::none() — a whole-call marker for with(), not a per-position
 * matcher like any()/type()/satisfies(): it means "this call takes no
 * arguments at all" rather than "this one position matches anything." Kept
 * as its own matcher, same reasoning as RemainingMatcher, so with() needs no
 * special casing to accept it — MethodExpectation::matchesArguments() is
 * what actually special-cases it, short-circuiting to an empty-arguments
 * check before the positional count comparison runs, since a single
 * NoneMatcher constraint isn't a real position to compare a real argument
 * against.
 *
 * MethodExpectation::with() rejects this unless it's the only argument
 * passed — "no arguments, and also this value" isn't coherent.
 */
final class NoneMatcher implements Matcher
{
    public function matches(mixed $actual): bool
    {
        return true;
    }

    public function describe(): string
    {
        return 'no arguments';
    }

    public function explainMismatch(mixed $actual): ?string
    {
        return null;
    }
}
