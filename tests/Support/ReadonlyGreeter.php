<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * Declares a readonly property its subclass (InheritedReadonlyGreeter) only
 * inherits — used to prove passthru()'s copyState() can initialize a
 * readonly property on a double of a class that doesn't itself declare it.
 */
abstract class ReadonlyGreeter
{
    public function __construct(protected readonly string $name) {}

    public function greet(): string
    {
        return "Hello, {$this->name}!";
    }
}
