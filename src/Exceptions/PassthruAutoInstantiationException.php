<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when ->passthru() is called with no argument and reflection-based
 * auto-instantiation of the target fails.
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
        return new self($target, "It's an interface — so there's no constructor to invoke.");
    }

    public static function constructionFailed(string $target, \Throwable $exception): self
    {
        return new self($target, sprintf('Constructing it threw: "%s".', $exception->getMessage()));
    }

    private function render(): string
    {
        return sprintf(
            'Can\'t auto-instantiate `%s` to passthru. %s You may need to pass an existing instance '
            .'instead. For example: `->passthru($existingInstance)`.',
            $this->target,
            $this->reason,
        );
    }
}
