<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class HasMagicMethod
{
    public function __toString(): string
    {
        return 'real';
    }

    public function greet(): string
    {
        return 'hello';
    }
}
