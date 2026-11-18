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
        return self::join(array_map(ValueFormatter::describe(...), $arguments));
    }

    /**
     * A string key is written the way a named argument is: `name: value`.
     *
     * @param  array<int|string, string>  $descriptions
     */
    public static function join(array $descriptions): string
    {
        foreach ($descriptions as $name => $description) {
            if (is_string($name)) {
                $descriptions[$name] = $name.': '.$description;
            }
        }

        return implode(', ', $descriptions);
    }
}
