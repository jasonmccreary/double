<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

abstract class AbstractStaticMethodClass
{
    abstract public static function make(): static;

    abstract protected static function build(): never;

    public function greet(): string
    {
        return 'Hello';
    }
}
