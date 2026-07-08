<?php

declare(strict_types=1);

namespace TestDouble\Tests\Fixtures;

final class Book
{
    public function __construct(
        public readonly string $title,
    ) {
    }
}
