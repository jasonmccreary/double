<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface ReceivedCollisionInterface
{
    public function received(): bool;
}
