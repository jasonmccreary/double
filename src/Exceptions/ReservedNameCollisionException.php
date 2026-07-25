<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown at TestDouble::for() time when the target declares a real public
 * method with the same name as one of TestDouble's own control verbs
 * (expects, allows, strict, passthru, received, verify) — a deliberate,
 * permanent trade-off (see DoubleControlMethods), not a later hardening pass.
 */
final class ReservedNameCollisionException extends \LogicException
{
    /**
     * @param  string[]  $collisions
     */
    public static function forCollisions(string $target, array $collisions): self
    {
        $backtickedNames = array_map(static fn (string $name): string => "`{$name}`", $collisions);

        $last = array_pop($backtickedNames);
        $names = $backtickedNames === [] ? $last : implode(', ', $backtickedNames).' and '.$last;

        return new self(sprintf(
            'Can\'t create a test double for `%s`. It contains %s which %s with TestDouble\'s internal methods.',
            $target,
            $names,
            count($collisions) === 1 ? 'collides' : 'collide',
        ));
    }
}
