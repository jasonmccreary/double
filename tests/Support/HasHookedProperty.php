<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class HasHookedProperty
{
    public string $name = 'Alice' {
        get => strtoupper($this->name);
    }

    public function greet(): string
    {
        return 'hello';
    }
}
