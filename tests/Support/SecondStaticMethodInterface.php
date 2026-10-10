<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface SecondStaticMethodInterface
{
    public static function make(): string;

    public static function &shared(int ...$ids): array;

    public static function abort(): never;
}
