<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface MagicMethodInterface
{
    public function __call(string $name, array $arguments): mixed;

    public function greet(): string;
}
