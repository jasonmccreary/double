<?php

declare(strict_types=1);

namespace JMac\Testing;

use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Engine\ReceivedAssertion;

/**
 * An opaque snapshot of the auto-verification state `Double::armAutoVerify()`
 * arms and `Double::verifyAll()` drains — the doubles created (and
 * `received()` assertions made) since the scope was armed. Meaningless on its
 * own; the only supported use is round-tripping it through
 * `Double::captureAutoVerifyScope()` and `Double::restoreAutoVerifyScope()`.
 *
 * Exists for runners that don't execute one test straight through from arm to
 * verify — e.g. interleaved fibers/coroutines, where a runner needs to park
 * one test's in-flight state and swap another's in without the two mixing.
 * PHPUnit's own `VerifiesDoubles` never needs this: it always arms and
 * verifies within a single, uninterrupted test method.
 */
final class AutoVerifyScope
{
    /**
     * @internal Built only by Double::captureAutoVerifyScope(). $pending and
     * $pendingReceived hold Engine types with no public API of their own —
     * constructing a scope by hand is possible but pointless, since nothing
     * outside Double can produce a meaningful DoubleState or
     * ReceivedAssertion to put in one.
     *
     * @param  list<DoubleState>  $pending
     * @param  list<ReceivedAssertion>  $pendingReceived
     */
    public function __construct(
        private readonly bool $armed,
        private readonly array $pending,
        private readonly array $pendingReceived,
    ) {}

    /**
     * @internal Read only by Double::restoreAutoVerifyScope().
     */
    public function armed(): bool
    {
        return $this->armed;
    }

    /**
     * @internal Read only by Double::restoreAutoVerifyScope().
     *
     * @return list<DoubleState>
     */
    public function pending(): array
    {
        return $this->pending;
    }

    /**
     * @internal Read only by Double::restoreAutoVerifyScope().
     *
     * @return list<ReceivedAssertion>
     */
    public function pendingReceived(): array
    {
        return $this->pendingReceived;
    }
}
