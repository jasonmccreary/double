<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * expects()/allows() was configured for a method the double's target never
 * declared.
 */
final class UnknownMethodDiagnostic implements Diagnostic
{
    public function __construct(
        public readonly string $target,
        public readonly string $method,
    ) {}
}
