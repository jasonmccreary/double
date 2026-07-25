<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 *
 * Renders a single value for use inside a matcher's describe()/
 * explainMismatch() output, and, via ArgumentFormatter, for a whole
 * actual-call argument list too. Lives in Diagnostics — not Matching, which
 * has zero dependencies on the rest of the library, and not Engine, which
 * is where it used to sit duplicated under a different name — since
 * Diagnostics is the one module built to be the shared home for rendering
 * logic every other module can reach: Matching may depend on Diagnostics
 * (and nothing else), Engine already may depend on everything.
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
