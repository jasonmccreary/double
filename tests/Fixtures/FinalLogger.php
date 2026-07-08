<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

final class FinalLogger
{
    public function log(string $message): bool
    {
        return true;
    }
}
