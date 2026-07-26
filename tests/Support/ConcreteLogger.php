<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class ConcreteLogger
{
    public function __construct(string $requiredDependency)
    {
        // A real double must never need to satisfy this constructor —
        // if TestDouble::for() ever tries to call it for real, this
        // throws and the test fails loudly instead of silently.
        throw new \RuntimeException('The real constructor must never run for a test double.');
    }

    public function log(string $message): bool
    {
        return true;
    }
}
