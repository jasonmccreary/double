<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface DiskInterface
{
    public function get(string $path): ?string;
}
