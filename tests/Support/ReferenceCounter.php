<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class ReferenceCounter
{
    public function increment(int &$value = 0): void
    {
        $value++;
    }

    public function incrementBy(int $amount, int &...$values): void
    {
        foreach ($values as &$value) {
            $value += $amount;
        }
    }
}
