<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

abstract class StaticFactoryClass
{
    abstract public static function make(): static;

    public static function create(): static
    {
        return static::make();
    }

    public function fresh(): static
    {
        return static::make();
    }
}
