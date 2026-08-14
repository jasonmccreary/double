<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows()/received() is configured for a magic
 * method that exists on the double's target — except the fixed-signature
 * ones in ClassGenerator::DOUBLEABLE_MAGIC_METHODS (__invoke, __toString,
 * etc.), which are overridden like any other method and never reach this.
 * Everything else is never overridden (see
 * ClassGenerator::overridableMethods()), so an expectation configured here
 * could never be satisfied — rejected up front instead of failing
 * confusingly later.
 */
class MagicMethodException extends DoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $method,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::lead($this->render()));
    }

    private function render(): string
    {
        // No fabricatedNote() here, unlike its siblings: like the static-method
        // restriction, this holds regardless of how the double came to exist.
        return sprintf(
            'Can\'t configure `%s` on a double for `%s` since it\'s a magic method. Magic methods can\'t be doubled.',
            $this->method,
            $this->target,
        );
    }
}
