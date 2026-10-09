<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

trait MakesStatically
{
    abstract public static function make(): static;
}
