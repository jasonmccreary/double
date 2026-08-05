<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown at Double::for() time when the target declares a real public
 * method with the same name as one of Double's own control verbs
 * (expects, allows, strict, passthru, received, unused, verify) — a deliberate,
 * permanent trade-off (see DoubleControlMethods), not a later hardening pass.
 */
class ReservedNameCollisionException extends DoubleException
{
    /**
     * @param  string[]  $collisions
     */
    public function __construct(
        public readonly string $target,
        public readonly array $collisions,
    ) {
        parent::__construct($this->render());
    }

    /**
     * @param  string[]  $collisions
     */
    public static function forCollisions(string $target, array $collisions): self
    {
        return new self($target, $collisions);
    }

    private function render(): string
    {
        $backtickedNames = array_map(static fn (string $name): string => "`{$name}`", $this->collisions);

        $last = array_pop($backtickedNames);
        $names = $backtickedNames === [] ? $last : implode(', ', $backtickedNames).' and '.$last;

        return sprintf(
            'Can\'t create a double for `%s`. It contains %s which %s with Double\'s internal methods.',
            $this->target,
            $names,
            count($this->collisions) === 1 ? 'collides' : 'collide',
        );
    }
}
