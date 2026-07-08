<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

interface VariadicInterface
{
    public function combine(string $glue, string ...$parts): string;
}
