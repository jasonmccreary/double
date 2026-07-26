<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface LoggerInterface
{
    public function log(string $message): bool;
}
