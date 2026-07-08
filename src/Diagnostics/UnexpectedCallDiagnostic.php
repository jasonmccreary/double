<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * Strict mode: a call matched no configured expects()/allows() expectation.
 * See ARCHITECTURE.md, "Modes: Loose, Strict, Passthru."
 */
final class UnexpectedCallDiagnostic implements Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
    ) {}
}
