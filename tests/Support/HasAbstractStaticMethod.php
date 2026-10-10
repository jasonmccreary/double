<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

abstract class HasAbstractStaticMethod
{
    abstract public static function make(): static;

    public function greet(): string
    {
        return 'Hello';
    }
}
