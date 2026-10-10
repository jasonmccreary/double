<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface EnumExtendingInterface extends \BackedEnum
{
    public function label(): string;
}
