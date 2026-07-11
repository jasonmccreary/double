<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown at TestDouble::for() time when the requested target cannot be
 * doubled at all: it doesn't exist, or it's a final class (which can't be
 * extended). See ARCHITECTURE.md, "Known scaffold-era limitations."
 */
class InvalidDoubleTargetException extends TestDoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $reason,
    ) {
        parent::__construct($this->render());
    }

    public static function doesNotExist(string $target): self
    {
        return new self($target, 'no such class or interface exists');
    }

    public static function isFinal(string $target): self
    {
        return new self($target, "it's final, so it can't be extended");
    }

    /**
     * TestDouble::for() with more than one target only accepts interfaces —
     * mirrors PHP's own intersection-type rule (a class can extend at most
     * one parent, so combining several targets into one double only ever
     * works via multiple `implements`, which requires every one of them to
     * be an interface).
     */
    public static function mustBeInterface(string $target): self
    {
        return new self($target, "it's a class — every target passed to for() together must be an interface");
    }

    public static function duplicateTarget(string $target): self
    {
        return new self($target, 'it was passed more than once');
    }

    private function render(): string
    {
        return sprintf(
            'Can\'t create a test double for "%s": %s.',
            $this->target,
            $this->reason,
        );
    }
}
