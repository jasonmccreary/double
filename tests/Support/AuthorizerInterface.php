<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * A confirmed, not hypothetical, real-world collision with Double's own
 * control API: authorization/policy interfaces routinely declare a real
 * allows($ability, ...$args): bool method (Laravel's own Gate contract uses
 * this exact verb).
 */
interface AuthorizerInterface
{
    public function allows(string $ability, mixed ...$arguments): bool;
}
