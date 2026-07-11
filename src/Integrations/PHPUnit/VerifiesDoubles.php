<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\TestDouble;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Opt-in auto-verification for PHPUnit users: add `use VerifiesDoubles;` to
 * a base TestCase once, and every expects() expectation on every double
 * created anywhere during a test gets checked automatically — no manual
 * $double->verify() call needed. Mirrors what Mockery's own
 * MockeryPHPUnitIntegration trait does for Mockery::close().
 *
 * Hooks PHPUnit's #[Before]/#[After] lifecycle attributes rather than
 * overriding setUp()/tearDown(): those methods run regardless of whatever
 * the test class's own setUp()/tearDown() does, with no
 * parent::setUp()/parent::tearDown() boilerplate required to avoid silently
 * skipping them.
 *
 * Both hooks are required, not just #[After]: TestDouble::$pending has to
 * be a plain list holding real references (not a WeakMap like the
 * double->state map) because a double that's purely a local variable in the
 * test method — the overwhelming common case — is already
 * garbage-collected the instant that method returns, well before #[After]
 * ever runs, and would already be gone from a WeakMap by then (confirmed
 * empirically: an earlier version that just re-walked the existing
 * double->state WeakMap in #[After] passed a test with a genuinely unmet
 * expectation). armAutoVerify() is what makes that list start capturing
 * every double created for the rest of this test, before the test body
 * runs.
 *
 * There is deliberately no equivalent PHPUnit "Extension" (the newer
 * bootstrap/event-subscriber mechanism configured via phpunit.xml's
 * <extensions>). Checked directly against the installed phpunit/phpunit
 * source: Runner\Extension\Facade only exposes registering event
 * subscribers/tracers, which is read-only observability — a subscriber has
 * no way to retroactively fail a test. Only a method that runs inside
 * TestCase::runBare()'s own try/catch (setUp(), tearDown(),
 * assertPostConditions(), or a #[Before]/#[After] hook) can actually do
 * that. So this trait isn't a fallback for a "real" extension — it's the
 * only mechanism able to do this at all.
 *
 * $double->verify() remains available, and necessary, for every non-PHPUnit
 * test runner, and for PHPUnit users who'd rather verify explicitly.
 */
trait VerifiesDoubles
{
    #[Before]
    final public function armTestDoubleAutoVerification(): void
    {
        TestDouble::armAutoVerify();
    }

    #[After]
    final public function verifyDoublesCreatedDuringThisTest(): void
    {
        TestDouble::verifyAll();
    }
}
