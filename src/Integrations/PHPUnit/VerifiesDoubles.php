<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Double;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * There's deliberately no equivalent PHPUnit "Extension" (the
 * bootstrap/event-subscriber mechanism configured via phpunit.xml). Checked
 * directly against phpunit/phpunit's source: Runner\Extension\Facade only
 * exposes registering event subscribers, which is read-only observability —
 * a subscriber has no way to retroactively fail a test. Only a method
 * running inside TestCase::runBare()'s own try/catch (setUp(), tearDown(),
 * assertPostConditions(), or a #[Before]/#[After] hook) can do that, so this
 * trait isn't a fallback for a "real" extension — it's the only mechanism
 * able to do this at all.
 */
trait VerifiesDoubles
{
    // #[Before]/#[After], not setUp()/tearDown() overrides — these run
    // regardless of whatever the test class's own setUp()/tearDown() does,
    // with no parent::setUp()/parent::tearDown() boilerplate needed to
    // avoid silently skipping them.
    #[Before]
    final public function armDoubleAutoVerification(): void
    {
        // Double::$pending has to be a plain list of real references, not a
        // WeakMap — a double that's purely a local variable in the test method
        // (the common case) is already garbage-collected the instant that method
        // returns, well before #[After] runs, and would already be gone from a
        // WeakMap by then. Confirmed empirically: an earlier version that just
        // re-walked the double->state WeakMap in #[After] passed a test with a
        // genuinely unmet expectation.
        Double::armAutoVerify();
    }

    #[After]
    final public function verifyDoublesCreatedDuringThisTest(): void
    {
        // PHPUnit runs #[After] unconditionally — pass, fail, error, or skipped —
        // unlike assertPostConditions(), which PHPUnit skips once a test has
        // already failed. Without this check, a test that fails a plain assertion
        // before an already-registered expects()/allows() is satisfied would
        // report a second, unrelated-looking "unmet expectation" failure on top
        // of the real one.
        if (! $this->status()->isSuccess()) {
            // The next test's #[Before] resets $pending/$pendingReceived
            // unconditionally, so nothing here needs to drain them.
            return;
        }

        Double::verifyAll();
    }
}
