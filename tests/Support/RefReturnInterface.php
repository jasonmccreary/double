<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface RefReturnInterface
{
    public function &getRef(): int;
}
