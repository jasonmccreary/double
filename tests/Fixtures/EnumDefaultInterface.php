<?php

declare(strict_types=1);

namespace TestDouble\Tests\Fixtures;

interface EnumDefaultInterface
{
    public function draw(Suit $suit = Suit::Hearts): Suit;
}
