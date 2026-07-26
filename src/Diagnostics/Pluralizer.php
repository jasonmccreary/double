<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 */
final class Pluralizer
{
    public static function pluralize(int $count, string $singular, string $plural): string
    {
        return sprintf('%d %s', $count, $count === 1 ? $singular : $plural);
    }
}
