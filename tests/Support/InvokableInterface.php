<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface InvokableInterface
{
    public function __invoke(int $value): string;
}
