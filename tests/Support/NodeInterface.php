<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * Self-referential and non-nullable — the return type names the same
 * interface that declares it, which SafeDefaultResolver treats the same as
 * a `self` return (see its class docblock).
 */
interface NodeInterface
{
    public function next(): NodeInterface;
}
