<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Kept as its own matcher (same reasoning as RemainingMatcher) so with()
 * needs no special casing to accept it — MethodExpectation::matchesArguments()
 * is what special-cases it, short-circuiting to an empty-arguments check
 * before positional comparison runs.
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
