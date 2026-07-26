<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class HasStaticMethod
{
    public static function make(): string
    {
        return 'real';
    }

    public function greet(): string
    {
        return 'hello';
    }
}
