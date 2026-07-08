<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

/**
 * Self-referential and non-nullable — exercises both recursive fabrication
 * and the depth cap with one minimal fixture (root double -> fabricate
 * depth 1 -> fabricate depth 2 -> cap hit, null). See ARCHITECTURE.md,
 * "Modes: Loose, Strict, Passthru."
 */
interface NodeInterface
{
    public function next(): NodeInterface;
}
