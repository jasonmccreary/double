<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 */
final class ValueFormatter
{
    public static function describe(mixed $value): string
    {
        return match (true) {
            is_string($value), is_int($value), is_float($value), is_bool($value), $value === null => var_export($value, true),
            is_array($value) => sprintf('array(%d)', count($value)),
            default => get_debug_type($value),
        };
    }
}
