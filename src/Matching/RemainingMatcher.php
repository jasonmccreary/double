<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Kept as its own matcher rather than a second sense of any() —
 * MethodExpectation::matchesArguments() is what special-cases this as the
 * list's trailing element.
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
