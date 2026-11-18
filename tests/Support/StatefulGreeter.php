<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * A real constructor that sets a property greet() actually reads — used to
 * prove passthru() copies the supplied instance's state onto the double.
 */
class StatefulGreeter
{
    private string $name;

    public function __construct(string $name = 'World')
    {
        $this->name = $name;
    }

    public function greet(): string
    {
        return "Hello, {$this->name}!";
    }
}
