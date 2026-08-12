<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows()/received() is configured for a method the
 * double's target never declared. $suggestion, when not null, is the
 * closest declared method name found by Diagnostics\DidYouMean — close
 * enough that the caller likely mistyped it rather than meaning to
 * configure something that genuinely doesn't exist.
 */
class UnknownMethodException extends DoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $method,
        public readonly bool $fabricated = false,
        public readonly ?string $suggestion = null,
    ) {
        parent::__construct(self::lead($this->render()));
    }

    private function render(): string
    {
        return sprintf(
            'Can\'t configure `%s` on a double for `%s` because it doesn\'t declare this method.%s%s',
            $this->method,
            $this->target,
            $this->suggestion !== null ? sprintf(' Did you mean `%s`?', $this->suggestion) : '',
            self::fabricatedNote($this->fabricated),
        );
    }
}
