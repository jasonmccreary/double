<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * TestDouble::for() was asked to double a target that cannot be doubled at
 * all: it doesn't exist, or it's a final class. See ARCHITECTURE.md, "Known
 * scaffold-era limitations."
 */
final class InvalidDoubleTargetDiagnostic implements Diagnostic
{
    private function __construct(
        public readonly string $target,
        public readonly string $reason,
    ) {}

    public static function doesNotExist(string $target): self
    {
        return new self($target, 'no such class or interface exists');
    }

    public static function isFinal(string $target): self
    {
        return new self($target, 'it is declared final, so it cannot be doubled by extending it');
    }
}
