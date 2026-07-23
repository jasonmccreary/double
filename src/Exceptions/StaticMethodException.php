<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows()/received() is configured for a method that
 * exists on the double's target but is static. A generated double's
 * overridden methods only ever run through an instance (see
 * ClassGenerator::overridableMethods()'s docblock for why static methods
 * are never among them) — a static call to the double's target always
 * reaches the real, unstubbed implementation instead, so an expectation
 * configured here could never be satisfied, and a received() assertion
 * here could never see anything the real static method didn't actually do.
 * Rejected up front, the same way UnknownMethodException rejects a method
 * that does not exist at all, rather than left to fail confusingly later at
 * verify() time with no indication of why.
 */
class StaticMethodException extends TestDoubleException
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
            'Can\'t configure `%s` on a test double of `%s`: it\'s a static method, and test doubles only intercept instance calls.%s',
            $this->method,
            $this->target,
            self::fabricatedNote($this->fabricated),
        );
    }
}
