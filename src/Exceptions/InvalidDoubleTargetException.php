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

    /**
     * A static method has no instance to dispatch through, so
     * ClassGenerator never overrides one — see its own docblock. That's a
     * silent no-op for a concrete class (the real static implementation
     * just keeps running, inherited as-is), but an abstract static method
     * (always abstract on an interface; possibly abstract on an abstract
     * class too) leaves the generated class with an inherited abstract
     * method it never implements — a PHP fatal error at eval() time, not a
     * catchable exception, unless this check catches it first.
     */
    public static function hasAbstractStaticMethod(string $target, string $method): self
    {
        return new self($target, sprintf(
            'it declares a static method (`%s`) with no implementation to fall back on — static methods can\'t be doubled',
            $method,
        ));
    }

    private function render(): string
    {
        return sprintf(
            'Can\'t create a test double for `%s`: %s.',
            $this->target,
            $this->reason,
        );
    }
}
