<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

trait ClassNameDefaultTrait
{
    public function withClassName(string $name = __CLASS__): string
    {
        return $name;
    }
}
