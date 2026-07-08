<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * @internal
 *
 * Renders a single value for use inside a matcher's describe()/
 * explainMismatch() output. Deliberately separate from
 * Engine\ArgumentFormatter (which renders a whole actual-call argument
 * list) rather than shared with it — Matching has zero dependencies on the
 * rest of the library, so it cannot reach into Engine for this, even for a
 * few lines of overlap.
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
