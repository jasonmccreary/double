<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

abstract class ImplementsStaticMethodInterface implements StaticMethodInterface
{
    public function greet(): string
    {
        return 'Hello';
    }
}
