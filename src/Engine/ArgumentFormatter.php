<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

/**
 * @internal
 *
 * Turns raw call arguments into readable text for exception messages.
 * Deliberately minimal placeholder — ARCHITECTURE.md's Diagnostics module
 * (M3) will own real value rendering; Exceptions itself must stay
 * string-only (it has zero dependency on Engine), so this formatting lives
 * here and callers pass Exceptions a plain string.
 */
final class ArgumentFormatter
{
    public static function describe(array $arguments): string
    {
        return implode(', ', array_map(self::describeOne(...), $arguments));
    }

    private static function describeOne(mixed $value): string
    {
        return match (true) {
            is_string($value), is_int($value), is_float($value), is_bool($value), $value === null => var_export($value, true),
            is_array($value) => sprintf('array(%d)', count($value)),
            default => get_debug_type($value),
        };
    }
}
