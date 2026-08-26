<?php

declare(strict_types=1);

namespace JMac\Testing;

use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Engine\ReceivedAssertion;

/**
 * An opaque snapshot of the auto-verification state `Double::enableAutoVerify()`
 * turns on and `Double::verifyAll()` drains — the doubles created (and
 * `received()` assertions made) since auto-verify was enabled. Meaningless on
 * its own; the only supported use is round-tripping it through
 * `Double::pauseAutoVerify()` and `Double::resumeAutoVerify()`.
 *
 * Exists for runners that don't execute one test straight through from enable
 * to verify — e.g. interleaved fibers/coroutines, where a runner needs to
 * pause one test's in-flight state and swap another's in without the two
 * mixing. PHPUnit's own `VerifiesDoubles` never needs this: it always enables
 * and verifies within a single, uninterrupted test method.
 */
final class AutoVerifySnapshot
{
    /**
     * @internal Built only by Double::pauseAutoVerify(). $pending and
     * $pendingReceived hold Engine types with no public API of their own —
     * constructing a snapshot by hand is possible but pointless, since
     * nothing outside Double can produce a meaningful DoubleState or
     * ReceivedAssertion to put in one.
     *
     * @param  list<DoubleState>  $pending
     * @param  list<ReceivedAssertion>  $pendingReceived
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly array $pending,
        private readonly array $pendingReceived,
    ) {}

    /**
     * @internal Read only by Double::resumeAutoVerify().
     */
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @internal Read only by Double::resumeAutoVerify().
     *
     * @return list<DoubleState>
     */
    public function pending(): array
    {
        return $this->pending;
    }

    /**
     * @internal Read only by Double::resumeAutoVerify().
     *
     * @return list<ReceivedAssertion>
     */
    public function pendingReceived(): array
    {
        return $this->pendingReceived;
    }
}
