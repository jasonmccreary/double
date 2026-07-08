<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\UnconfiguredReturnDiagnostic;

/**
 * ARCHITECTURE.md's "Sensible defaults" section calls for a single
 * safe-default-by-return-type resolver used both by Loose mode's fallback
 * and by any matched expectation missing an explicit ->returns() /
 * ->throws() / ->returnsUsing(). That resolver is explicitly M4 scope
 * (bundled with Loose's fabrication machinery). Reviving the old "just
 * return null" shortcut in the meantime would silently recreate the exact
 * bug that resolver is meant to fix, so this fails loudly here instead.
 */
final class UnconfiguredReturnException extends TestDoubleException
{
    public static function forCall(string $label, string $method): self
    {
        return new self(new UnconfiguredReturnDiagnostic($label, $method));
    }
}
