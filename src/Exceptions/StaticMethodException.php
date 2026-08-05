<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows()/received() is configured for a static
 * method on the double's target.
 */
class StaticMethodException extends DoubleException
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
        // No fabricatedNote() here, unlike its siblings: the static-method
        // restriction holds regardless of how the double came to exist, so
        // there's nothing true left to add about auto-fabrication.
        return sprintf(
            'Can\'t configure `%s` on a double for `%s` since it\'s a static method. Static methods can\'t be doubled.',
            $this->method,
            $this->target,
        );
    }
}
