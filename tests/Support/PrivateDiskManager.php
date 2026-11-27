<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class PrivateDiskManager
{
    private function get(string $name): string
    {
        return $name;
    }

    public function __call(string $method, array $parameters): mixed
    {
        return null;
    }
}
