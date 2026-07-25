<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface ByRefParamInterface
{
    public function increment(int &$value): void;
}
