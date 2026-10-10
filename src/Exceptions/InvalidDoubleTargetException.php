<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown at Double::for() time when the requested target cannot be
 * doubled at all: it doesn't exist, or it's a final class (which can't be
 * extended).
 */
class InvalidDoubleTargetException extends DoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $reason,
    ) {
        parent::__construct(self::lead($this->render()));
    }

    public static function doesNotExist(string $target): self
    {
        return new self($target, 'no such class or interface exists');
    }

    /**
     * Not necessarily permanent — Double::bypassFinals(), called before this
     * target is ever loaded anywhere in the process, rewrites `final class`
     * out of its source so ClassGenerator's own reflection check never sees
     * it in the first place (see that check's comment in
     * ClassGenerator::generate()). The message names that escape hatch
     * directly, since "final and can't be extended" alone would otherwise
     * read as a dead end rather than something the caller can act on.
     */
    public static function isFinal(string $target): self
    {
        return new self($target, "it's final and can't be extended. You may use `Double::bypassFinals()`. Review the documentation for more detail: https://testdoublephp.com/creating-doubles#doubling-a-final-class");
    }

    public static function mustBeInterface(string $target): self
    {
        return new self($target, "it's a class. When multiple targets are passed to `Double::for()`, they must all be interfaces");
    }

    public static function duplicateTarget(string $target): self
    {
        return new self($target, 'it was passed more than once');
    }

    /**
     * A class with an unimplemented static method, declared by the class
     * itself or inherited from an interface. An interface's own abstract
     * static method is stubbed by ClassGenerator, not rejected.
     */
    public static function hasAbstractStaticMethod(string $target, string $method): self
    {
        return new self($target, sprintf(
            'it has an abstract static method (`%s`). Double a concrete subclass that implements it instead',
            $method,
        ));
    }

    /**
     * Intersection targets that declare the same method static on one and
     * as an instance method on another — no class can implement both.
     */
    public static function hasStaticInstanceClash(string $target, string $method): self
    {
        return new self($target, sprintf(
            'it declares `%s` as both a static and an instance method. No class can implement both',
            $method,
        ));
    }

    public static function isEnumInterface(string $target): self
    {
        return new self($target, 'only an enum can implement `UnitEnum` or `BackedEnum`');
    }

    /**
     * A magic method outside ClassGenerator::DOUBLEABLE_MAGIC_METHODS is
     * never overridden, so an abstract one would leave the generated class
     * incomplete — caught here instead of surfacing as an uncatchable
     * eval()-time error.
     */
    public static function hasAbstractMagicMethod(string $target, string $method): self
    {
        return new self($target, sprintf(
            'it declares a magic method (`%s`). Magic methods can\'t be doubled',
            $method,
        ));
    }

    /**
     * Same failure shape again, for PHP 8.4+ hooked properties — PHP
     * represents an unimplemented hook internally like a synthetic
     * abstract method (confirmed directly: the fatal error names it
     * `Interface::$property::get`), and ClassGenerator never reasons about
     * properties at all.
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
            'Can\'t create a double for `%s` since %s.',
            $this->target,
            $this->reason,
        );
    }
}
