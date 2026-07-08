<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * Setup code tried to set a double's mode more than once. See
 * ARCHITECTURE.md, "Modes: Loose, Strict, Passthru" — a double's mode is
 * set once and is immutable after that.
 */
final class ModeConfigurationDiagnostic implements Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $current,
        public readonly string $attempted,
    ) {}
}
