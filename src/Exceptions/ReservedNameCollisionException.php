<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown at TestDouble::for() time when the target declares a real public
 * method with the same name as one of TestDouble's own control verbs
 * (expects, allows, strict, passthru, received). See ARCHITECTURE.md,
 * "Class surface area" — this is a closed decision, not a later hardening
 * pass.
 */
final class ReservedNameCollisionException extends \LogicException
{
    /**
     * @param  string[]  $collisions
     */
    public static function forCollisions(string $target, array $collisions): self
    {
        return new self(sprintf(
            'Can\'t create a test double for "%s": %s collides with TestDouble\'s own '
            .'control verbs (expects/allows/strict/passthru/received) — a method can\'t be '
            .'both a real one and a configuration verb.',
            $target,
            implode(', ', $collisions),
        ));
    }
}
