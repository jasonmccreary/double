<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface SelfTypedStaticMethodInterface
{
    public const STRICT = 1;

    public static function compare(self $left, self $right, int $mode = self::STRICT): bool;
}
