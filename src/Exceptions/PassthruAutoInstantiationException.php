<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when ->passthru() is called with no argument and reflection-based
 * auto-instantiation of the target fails. See ARCHITECTURE.md, "Passthru."
 */
class PassthruAutoInstantiationException extends TestDoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $reason,
    ) {
        parent::__construct($this->render());
    }

    public static function isInterface(string $target): self
    {
        return new self($target, "it's an interface — no constructor to invoke");
    }

    public static function constructionFailed(string $target, \Throwable $exception): self
    {
        return new self($target, sprintf('constructing it threw: %s', $exception->getMessage()));
    }

    private function render(): string
    {
        return sprintf(
            'Can\'t auto-instantiate "%s" to passthru to: %s. '
            .'Pass an existing instance instead: ->passthru($existingInstance).',
            $this->target,
            $this->reason,
        );
    }
}
