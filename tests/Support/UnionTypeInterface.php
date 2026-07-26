<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface UnionTypeInterface
{
    public function accept(int|string $value): int|string;

    public function acceptNullableUnion(int|string|null $value): int|string|null;
}
