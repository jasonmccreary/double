<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * ARCHITECTURE.md's "Sensible defaults" section calls for a single
 * safe-default-by-return-type resolver used both by Loose mode's fallback
 * and by any matched expectation missing an explicit ->returns() /
 * ->throws() / ->returnsUsing(). That resolver is explicitly M4 scope
 * (bundled with Loose's fabrication machinery). Reviving the old "just
 * return null" shortcut in the meantime would silently recreate the exact
 * bug that resolver is meant to fix, so M1 fails loudly here instead.
 */
final class UnconfiguredReturnException extends TestDoubleException
{
    public static function forCall(string $label, string $method): self
    {
        return new self(sprintf(
            'Test double "%s" matched a call to "%s" that has no configured returns()/throws()/'
            .'returnsUsing(). Automatic safe-default return values are not implemented until M4 '
            .'(see ARCHITECTURE.md) — configure an explicit return for this expectation for now.',
            $label,
            $method,
        ));
    }
}
