<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

interface EnumDefaultInterface
{
    public function draw(Suit $suit = Suit::Hearts): Suit;
}
