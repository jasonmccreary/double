<?php

declare(strict_types=1);

namespace TestDouble\Tests\Fixtures;

interface VariadicInterface
{
    public function combine(string $glue, string ...$parts): string;
}
