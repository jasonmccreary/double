<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class Aborter
{
    public function abort(): never
    {
        throw new \RuntimeException('real abort');
    }
}
