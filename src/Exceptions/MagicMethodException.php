<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows()/received() is configured for a magic
 * method (__toString, __invoke, __call, etc.) that exists on the double's
 * target. ClassGenerator::overridableMethods() never overrides a magic
 * method — see its own docblock — so a generated double's magic methods
 * either don't exist at all (an interface's abstract one is caught earlier,
 * at TestDouble::for() time, by InvalidDoubleTargetException::
 * hasAbstractMagicMethod()) or fall through to the real, inherited
 * implementation unchanged. Either way, an expectation configured here
 * could never be satisfied, and a received() assertion here could never see
 * anything the real magic method didn't actually do. Rejected up front, the
 * same way StaticMethodException rejects a static method for the identical
 * underlying reason: the method exists, but is structurally impossible for
 * this library to intercept.
 */
class MagicMethodException extends TestDoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $method,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct($this->render());
    }

    private function render(): string
    {
        // No fabricatedNote() here, unlike its siblings: like the static-method
        // restriction, this holds regardless of how the double came to exist.
        return sprintf(
            'Can\'t configure `%s` on a test double for `%s` since it\'s a magic method. Magic methods can\'t be doubled.',
            $this->method,
            $this->target,
        );
    }
}
