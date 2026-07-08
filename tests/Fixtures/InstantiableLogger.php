<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

/**
 * A non-final class with a real, zero-argument constructor — used to prove
 * ->passthru()'s reflection-based auto-instantiation actually succeeds and
 * delegates, as opposed to ConcreteLogger (whose constructor always throws,
 * used for the auto-instantiation *failure* path) or FinalLogger (which
 * can't be doubled at all).
 */
class InstantiableLogger
{
    public function log(string $message): bool
    {
        return true;
    }
}
