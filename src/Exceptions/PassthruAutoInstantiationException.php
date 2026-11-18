<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when ->passthru() is called on an interface target, with or without
 * an instance — it is rejected outright, since an interface has no real
 * method bodies at all for ClassGenerator to have generated a "__td_real_*"
 * sibling for (see ClassGenerator::buildRealMethod()). A class, even one
 * whose constructor needs arguments this has no way to supply, is never
 * rejected here — see PassthruInitializer::assertNotInterface().
 */
class PassthruAutoInstantiationException extends DoubleException
{
    public function __construct(
        public readonly string $target,
        public readonly string $reason,
    ) {
        parent::__construct(self::lead($this->render()));
    }

    public static function isInterface(string $target): self
    {
        return new self($target, "It's an interface, so there's no real method to run.");
    }

    private function render(): string
    {
        return sprintf(
            'Can\'t use passthru on `%s`. %s You may double an existing instance instead. '
            .'For example: `Double::for($existingInstance)->passthru()`.',
            $this->target,
            $this->reason,
        );
    }
}
