<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * Real-world precedent per ARCHITECTURE.md: Laravel's own
 * Request::expectsJson() is an "expects" verb on a mainstream interface,
 * though this fixture declares the exact colliding name directly.
 */
interface ExpectsCollisionInterface
{
    public function expects(): bool;
}
