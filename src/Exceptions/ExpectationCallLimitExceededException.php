<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when a call matches a configured expectation, but accepting it
 * would exceed that expectation's configured maximum call count (e.g. a
 * fourth call to something configured with ->times(3), or any call at all
 * to something configured with ->never()).
 */
class ExpectationCallLimitExceededException extends TestDoubleException
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly int $maximum,
        public readonly int $callNumber,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct($this->render());
    }

    private function render(): string
    {
        return sprintf(
            'Test double "%s" received call #%d to "%s(%s)", but the matching expectation '
            .'allows at most %d call(s).%s',
            $this->label,
            $this->callNumber,
            $this->method,
            $this->argumentsDescription,
            $this->maximum,
            $this->fabricatedNote($this->fabricated),
        );
    }
}
