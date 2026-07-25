<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface MagicMethodInterface
{
    public function __toString(): string;

    public function greet(): string;
}
