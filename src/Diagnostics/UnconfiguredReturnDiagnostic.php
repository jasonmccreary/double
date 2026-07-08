<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * A call matched a configured expectation that never had returns()/throws()/
 * returnsUsing() configured. See ARCHITECTURE.md, "Sensible defaults" —
 * the safe-default-by-return-type resolver that would otherwise cover this
 * is M4 scope.
 */
final class UnconfiguredReturnDiagnostic implements Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
    ) {}
}
