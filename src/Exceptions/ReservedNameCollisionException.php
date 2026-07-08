<?php

declare(strict_types=1);

namespace TestDouble\Exceptions;

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
     * @param string[] $collisions
     */
    public static function forCollisions(string $target, array $collisions): self
    {
        return new self(sprintf(
            'Cannot create a test double for "%s": it declares method(s) %s, '
            . 'which collide with TestDouble\'s own control API and cannot be '
            . 'both a real interface method and a configuration verb on the '
            . 'same object.',
            $target,
            implode(', ', $collisions),
        ));
    }
}
