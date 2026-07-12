<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * A non-final class implementing LoggerInterface, with a real, zero-argument
 * constructor — used to prove that TestDouble::for($realInstance) derives a
 * double from the instance's own concrete class, and that the result still
 * satisfies whatever interface that class implements. That's just PHP's own
 * transitive interface inheritance through extends, not anything this
 * library does specially — see ClassGenerator, which generates
 * `class Generated extends RealLogger`.
 */
class RealLogger implements LoggerInterface
{
    public function log(string $message): bool
    {
        return true;
    }
}
