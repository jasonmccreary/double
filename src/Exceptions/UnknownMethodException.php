<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows() is configured for a method the double's
 * target never declared.
 */
class UnknownMethodException extends TestDoubleException
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
        return sprintf(
            'Cannot configure "%s" on a test double of "%s": no such method is declared there.%s',
            $this->method,
            $this->target,
            $this->fabricatedNote($this->fabricated),
        );
    }
}
