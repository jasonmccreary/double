<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 */
final class ArgumentFormatter
{
    public static function describe(array $arguments): string
    {
        return implode(', ', array_map(ValueFormatter::describe(...), $arguments));
    }
}
