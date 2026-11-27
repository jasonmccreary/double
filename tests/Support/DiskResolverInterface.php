<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface DiskResolverInterface
{
    public function get(string $name): DiskInterface;
}
