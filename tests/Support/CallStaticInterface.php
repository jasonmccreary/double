<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface CallStaticInterface
{
    public static function __callStatic(string $name, array $arguments): mixed;
}
