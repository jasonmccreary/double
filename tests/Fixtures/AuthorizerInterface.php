<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

/**
 * The confirmed, not hypothetical, real-world collision called out in
 * ARCHITECTURE.md's "Class surface area" section: authorization/policy
 * interfaces routinely declare a real allows($ability, ...$args): bool
 * method (Laravel's own Gate contract uses this exact verb).
 */
interface AuthorizerInterface
{
    public function allows(string $ability, mixed ...$arguments): bool;
}
