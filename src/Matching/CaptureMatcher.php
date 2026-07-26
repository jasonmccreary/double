<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

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

    // Never fires from matches() — that's called speculatively on every candidate
    // expectation while the engine looks for the last-registered match (see
    // ProxyBehavior::findMatch()), so a losing candidate could capture a value for
    // a call it never actually served. The engine calls this itself, once, only on
    // the expectation it has already confirmed as the real match
    // (MethodExpectation::recordMatch()).
    public function capture(mixed $value): void
    {
        $this->reference = $value;
    }
}
