<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Argument::any() — matches every value, including null. Useful for
 * pinning down one argument position while leaving the rest of a with()
 * call unconstrained.
 */
final class AnyMatcher implements Matcher
{
    public function matches(mixed $actual): bool
    {
        return true;
    }

    public function describe(): string
    {
        return 'any()';
    }

    public function explainMismatch(mixed $actual): ?string
    {
        return null;
    }
}
