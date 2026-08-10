<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

readonly class ReadOnlyLogger
{
    public function log(string $message): bool
    {
        return true;
    }
}
