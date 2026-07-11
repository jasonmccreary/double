<?php

declare(strict_types=1);

namespace JMac\Testing;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Engine\ArgumentFormatter;
use JMac\Testing\Engine\ClassGenerator;
use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Engine\ExceptionFactory;
use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Engine\ReceivedAssertion;
use JMac\Testing\Exceptions\UnknownMethodException;

/**
 * The public facade and the library's sole entry point — deliberately a
 * top-level class (`JMac\Testing\TestDouble`, not nested under `Engine\`)
 * since it's the one class every consumer touches directly; everything it
 * delegates to (`ClassGenerator`, `ProxyBehavior`, `DoubleState`,
 * `MethodExpectation`) stays internal under Engine. `TestDouble::for()`
 * creates a double; `$double->verify()` (see `DoubleControlMethods`) is the
 * manual verification call every test runner can use (see ARCHITECTURE.md,
 * "PHPUnit integration" — a framework-specific auto-verify extension is
 * future, additive work, not M1).
 */
final class TestDouble
{
    private static ?\WeakMap $states = null;

    /**
     * Strong references, deliberately not a WeakMap like $states: a double
     * that's purely a local variable in a test method (the overwhelming
     * common case) is already garbage-collected — and therefore already
     * gone from a WeakMap — the instant that method returns, well before
     * any #[After] hook runs. This list has to outlive that gap, which
     * means holding a real reference. Only appended to while
     * $autoVerifyArmed is true (see armAutoVerify()), so a suite that never
     * uses Integrations\PHPUnit\VerifiesDoubles never pays for this at all
     * — otherwise every double ever created, for the life of the whole
     * suite, would sit here unreleased.
     *
     * Holds DoubleState directly, not the double object: create() already
     * has the state in scope when it decides to push here, and verifying
     * only ever needs the state (see verifyState()) — going back through
     * $states to re-fetch it from the double would be a pointless second
     * lookup of data already in hand, and would needlessly couple this
     * list's correctness to $states still having the entry.
     *
     * @var list<DoubleState>
     */
    private static array $pending = [];

    private static bool $autoVerifyArmed = false;

    private function __construct() {}

    public static function for(string $target): object
    {
        return self::fabricate($target, depth: 0);
    }

    /**
     * @internal used only by SafeDefaultResolver for recursive Loose-mode
     * fabrication. depth=0 is exactly TestDouble::for()'s own public path —
     * a depth-0 double is never marked fabricated.
     */
    public static function fabricate(string $target, int $depth): object
    {
        $generatedClass = (new ClassGenerator)->generate($target);

        return self::create($generatedClass, $target, $depth);
    }

    /**
     * @internal used only by SafeDefaultResolver for intersection-typed
     * fabrication (see ClassGenerator::generateForIntersection()).
     *
     * @param  list<string>  $targets
     */
    public static function fabricateIntersection(array $targets, int $depth): object
    {
        $generatedClass = (new ClassGenerator)->generateForIntersection($targets);

        return self::create($generatedClass, implode('&', $targets), $depth);
    }

    private static function create(string $generatedClass, string $target, int $depth): object
    {
        $state = new DoubleState($target, self::deriveLabel($target));

        if ($depth > 0) {
            $state->markFabricated($depth);
        }

        $instance = $generatedClass::__td_instantiate();

        self::states()[$instance] = $state;

        if (self::$autoVerifyArmed) {
            self::$pending[] = $state;
        }

        return $instance;
    }

    /**
     * @internal used only by DoubleControlMethods::verify() — the double's
     * own verify() is the sole public entry point for verification (see the
     * no-alias policy in CONTRIBUTING.md); this static method exists only
     * because the verification logic needs access to the private
     * double->state map, which DoubleControlMethods cannot reach directly.
     */
    public static function verify(object $double): void
    {
        self::verifyState(self::stateFor($double));
    }

    private static function verifyState(DoubleState $state): void
    {
        $unmet = $state->unmetExpectations();

        if ($unmet === []) {
            return;
        }

        throw ExceptionFactory::unsatisfiedExpectation(
            $state->label(),
            array_map(
                static fn (MethodExpectation $expectation): UnsatisfiedExpectation => new UnsatisfiedExpectation(
                    method: $expectation->method(),
                    description: $expectation->describe(),
                    expectedMin: $expectation->minimumCalls(),
                    expectedMax: $expectation->maximumCalls(),
                    timesCalled: $expectation->timesMatched(),
                    otherObservedCalls: array_map(
                        ArgumentFormatter::describe(...),
                        $state->callsFor($expectation->method()),
                    ),
                ),
                $unmet,
            ),
            $state->isFabricated(),
        );
    }

    /**
     * @internal used only by Integrations\PHPUnit\VerifiesDoubles's #[Before]
     * hook. Arms $pending tracking (idempotent — safe to call every test,
     * not just once) and resets it fresh, so a prior test that somehow
     * skipped its own #[After] (e.g. a fatal error mid-test that bypassed
     * normal lifecycle hooks entirely) can never leak stale entries into
     * this one.
     */
    public static function armAutoVerify(): void
    {
        self::$autoVerifyArmed = true;
        self::$pending = [];
    }

    /**
     * @internal used only by Integrations\PHPUnit\VerifiesDoubles's #[After]
     * hook — see its docblock for why an automatic hook has to live there
     * and can't be a PHPUnit "Extension" instead. Verifies, then discards,
     * every double created since the last call (armAutoVerify() resets this
     * at the start of every test, so this is always exactly "this test's
     * doubles"). Drained up front, before iterating, so a verify() failure
     * partway through never leaves stale entries to leak into whichever
     * test's #[After] runs next.
     */
    public static function verifyAll(): void
    {
        $pending = self::$pending;
        self::$pending = [];

        foreach ($pending as $state) {
            self::verifyState($state);
        }
    }

    /**
     * @internal
     */
    public static function stateFor(object $double): DoubleState
    {
        $state = self::states()[$double] ?? null;

        if ($state === null) {
            throw new \LogicException('Object is not a TestDouble-generated double.');
        }

        return $state;
    }

    /**
     * @internal
     */
    public static function registerExpectation(object $double, string $method, bool $required): MethodExpectation
    {
        $state = self::stateFor($double);

        if ($state->declaringCandidate($method) === null) {
            throw new UnknownMethodException($state->target(), $method, $state->isFabricated());
        }

        $expectation = new MethodExpectation($method, $required);
        $state->registerExpectation($expectation);

        return $expectation;
    }

    /**
     * @internal used only by DoubleControlMethods::received() — the double's
     * own received() is the sole public entry point (see the no-alias policy
     * in CONTRIBUTING.md), same reasoning as verify(). Validates the method
     * name exactly like registerExpectation() does, so a typo in
     * received('sav') fails the same clear way expects()/allows() already do
     * — but, unlike registerExpectation(), never registers anything on
     * DoubleState: the returned ReceivedAssertion checks already-recorded
     * calls, it never participates in live matching or verify().
     */
    public static function received(object $double, string $method): ReceivedAssertion
    {
        $state = self::stateFor($double);

        if ($state->declaringCandidate($method) === null) {
            throw new UnknownMethodException($state->target(), $method, $state->isFabricated());
        }

        return new ReceivedAssertion($state, $method);
    }

    private static function states(): \WeakMap
    {
        return self::$states ??= new \WeakMap;
    }

    private static function deriveLabel(string $target): string
    {
        $position = strrpos($target, '\\');

        return $position === false ? $target : substr($target, $position + 1);
    }
}
