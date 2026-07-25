<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown at TestDouble::for() time when the requested target cannot be
 * doubled at all: it doesn't exist, or it's a final class (which can't be
 * extended).
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
        return new self($target, "it's final and can't be extended");
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
        return new self($target, "it's a class. When multiple targets are passed to `TestDouble::for()`, they must all be interfaces");
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
            'it declares a static method (`%s`). Static methods can\'t be doubled',
            $method,
        ));
    }

    /**
     * ClassGenerator::overridableMethods() never overrides a magic method
     * (__toString, __invoke, __call, even __construct/__destruct — anything
     * whose name starts with "__") — see its own docblock. That's a silent
     * no-op for a concrete class (the real implementation just keeps
     * running, inherited as-is), but an abstract magic method (always
     * abstract on an interface; possibly abstract on an abstract class too)
     * leaves the generated class with an inherited abstract method it never
     * implements — a PHP fatal error at eval() time, not a catchable
     * exception, unless this check catches it first. Same failure shape as
     * hasAbstractStaticMethod() above, for a different reason a method ends
     * up excluded from overriding.
     */
    public static function hasAbstractMagicMethod(string $target, string $method): self
    {
        return new self($target, sprintf(
            'it declares a magic method (`%s`). Magic methods can\'t be doubled',
            $method,
        ));
    }

    /**
     * PHP 8.4's property hooks let an interface require a hooked property
     * (`public string $name { get; }`) the same way it can require a
     * method — and PHP represents an unimplemented hook internally almost
     * like a synthetic abstract method for this exact purpose, confirmed
     * directly: the fatal error PHP raises names it
     * `Interface::$property::get`. `ClassGenerator` never reasons about
     * properties at all (only `getMethods()`), so the same "abstract
     * member excluded from overriding" crash the static-method and
     * magic-method checks already guard against was reachable a third way.
     * Caught here before eval() for the same reason those are.
     */
    public static function hasAbstractPropertyHook(string $target, string $property): self
    {
        return new self($target, sprintf(
            'it declares a hooked property (`%s`). Hooked properties can\'t be doubled',
            $property,
        ));
    }

    private function render(): string
    {
        return sprintf(
            'Can\'t create a test double for `%s` since %s.',
            $this->target,
            $this->reason,
        );
    }
}
