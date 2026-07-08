<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * A call matched a configured expectation, but accepting it would exceed
 * that expectation's configured maximum call count.
 */
final class CallLimitExceededDiagnostic implements Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly int $maximum,
        public readonly int $callNumber,
    ) {}
}
