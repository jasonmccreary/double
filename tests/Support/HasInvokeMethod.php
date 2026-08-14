<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class HasInvokeMethod
{
    public function __invoke(int $value): string
    {
        return 'real';
    }

    public function greet(): string
    {
        return 'hello';
    }
}
