<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 *
 * Turns a whole raw call argument list into readable text for exception
 * messages — the list-level entry point ProxyBehavior/TestDouble::verify()
 * actually call. Per-value rendering is ValueFormatter::describe(), used
 * here directly since both live in Diagnostics now (see its docblock).
 */
final class ArgumentFormatter
{
    public static function describe(array $arguments): string
    {
        return implode(', ', array_map(ValueFormatter::describe(...), $arguments));
    }
}
