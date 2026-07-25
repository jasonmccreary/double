<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Argument::capture($reference) — matches every value, like AnyMatcher, but
 * also writes it into the caller's variable. Mirrors Mockery::capture()
 * (adopted as the closest, most relevant prior art). $reference only ever
 * holds the most recently matched call's value; there's no history of
 * earlier calls.
 *
 * Capturing can't happen inside matches(): that method is called
 * speculatively on every candidate expectation while
 * the engine looks for the last-registered match (see
 * ProxyBehavior::findMatch()), so a losing candidate could write a value
 * for a call it never actually served. The engine instead calls capture()
 * itself, exactly once, only on the expectation it has already confirmed
 * as the real match (MethodExpectation::recordMatch()).
 */
final class CaptureMatcher implements Matcher
{
    public function __construct(
        private mixed &$reference,
    ) {}

    public function matches(mixed $actual): bool
    {
        return true;
    }

    public function describe(): string
    {
        return 'capture()';
    }

    public function explainMismatch(mixed $actual): ?string
    {
        return null;
    }

    public function capture(mixed $value): void
    {
        $this->reference = $value;
    }
}
