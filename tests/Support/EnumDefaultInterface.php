<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface EnumDefaultInterface
{
    public function draw(Suit $suit = Suit::Hearts): Suit;
}
