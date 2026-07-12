<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface StaticMethodInterface
{
    public static function make(): string;

    public function greet(): string;
}
