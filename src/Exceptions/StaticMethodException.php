<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows()/received() is configured for a static
 * method on the double's target, or when an abstract static method is
 * called on the double ($called) — it has no implementation to run.
 */
class StaticMethodException extends DoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $method,
        public readonly bool $fabricated = false,
        public readonly bool $called = false,
    ) {
        parent::__construct(self::lead($this->render()));
    }

    private function render(): string
    {
        // No fabricatedNote() here, unlike its siblings: the static-method
        // restriction holds regardless of how the double came to exist, so
        // there's nothing true left to add about auto-fabrication.
        return sprintf(
            'Can\'t %s `%s` on a double for `%s` since it\'s %s static method. Static methods can\'t be doubled.',
            $this->called ? 'call' : 'configure',
            $this->method,
            $this->target,
            $this->called ? 'an abstract' : 'a',
        );
    }
}
