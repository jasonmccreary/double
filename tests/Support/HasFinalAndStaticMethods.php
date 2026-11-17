<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class HasFinalAndStaticMethods extends HasStaticMethod
{
    final public function size(): int
    {
        return 42;
    }
}
