<?php

declare(strict_types=1);

namespace JMac\Testing;

use JMac\Testing\Diagnostics\ArgumentFormatter;
use JMac\Testing\Diagnostics\DidYouMean;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Engine\ClassGenerator;
use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Engine\ExceptionFactory;
use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Engine\ReceivedAssertion;
use JMac\Testing\Exceptions\MagicMethodException;
use JMac\Testing\Exceptions\StaticMethodException;
use JMac\Testing\Exceptions\UnknownMethodException;

/**
 * The public facade and the library's sole entry point. `TestDouble::for()`
 * creates a double; `$double->verify()` is the manual verification call
 * every test runner can use. PHPUnit users can skip it via
 * `Integrations\PHPUnit\VerifiesDoubles`, which auto-verifies every double
 * created during a test.
 */
final class TestDouble
{
    private static ?\WeakMap $states = null;

    /**
     * Strong references, not a WeakMap like $states — a double that's purely
     * a local variable in a test method (the common case) is already
     * garbage-collected, and therefore already gone from a WeakMap, the
     * instant that method returns, well before any #[After] hook runs. Only
     * appended to while $autoVerifyArmed is true, so a suite that never uses
     * VerifiesDoubles never pays for this at all. Holds the DoubleState
     * directly, not the double object, since create() already has it in
     * scope and verifying never needs anything else.
     *
     * @var list<DoubleState>
     */
    private static array $pending = [];

    /**
     * Same strong-reference reasoning as $pending, same lifecycle
     * (armAutoVerify() resets it, verifyAll() drains it) — the received()
     * counterpart, so both verbs get checked from the same #[After] hook.
     *
     * @var list<ReceivedAssertion>
     */
    private static array $pendingReceived = [];

    private static bool $autoVerifyArmed = false;

    private function __construct() {}

    /**
     * The real PHP return type stays the bare `object` — PHP has no syntax
     * for "whatever type this class-string names," only PHPStan/Psalm's
     * docblock generics do. That's sound, not just convenient: every
     * generated double actually `implements TestDoubleInterface` for real
     * (see that interface's own docblock), so the templated return below is
     * never a docblock fiction. Only precise for the single-target call — a
     * multi-target intersection call doesn't have a single T to infer from a
     * variadic template like this one, so it falls back to the same untyped
     * `object` a caller would have gotten before this existed.
     *
     * @template T of object
     *
     * @param  class-string<T>|T  $targets
     * @return T&TestDoubleInterface
     */
    public static function for(string|object ...$targets): object
    {
        if ($targets === []) {
            throw new \InvalidArgumentException('`TestDouble::for()` requires at least one target.');
        }

        if (count($targets) > 1) {
            foreach ($targets as $target) {
                if (is_object($target)) {
                    // Which real instance a later ->passthru() should fall back to
                    // becomes ambiguous the moment more than one target is involved,
                    // so mixing one into a multi-target call is rejected rather than
                    // guessing.
                    throw new \InvalidArgumentException(
                        '`TestDouble::for()` can\'t accept a real instance as a target when passing multiple targets.',
                    );
                }
            }

            return self::fabricateIntersection($targets, depth: 0);
        }

        $target = $targets[0];
        $knownInstance = is_object($target) ? $target : null;
        $targetClass = is_object($target) ? $target::class : $target;

        return self::fabricate($targetClass, depth: 0, knownInstance: $knownInstance);
    }

    /**
     * @internal Used by SafeDefaultResolver for recursive Loose-mode
     * fabrication. depth=0 is for()'s own public path — a depth-0 double is
     * never marked fabricated, and only that path ever passes $knownInstance.
     */
    public static function fabricate(string $target, int $depth, ?object $knownInstance = null): object
    {
        $generatedClass = (new ClassGenerator)->generate($target);

        return self::create($generatedClass, $target, $depth, $knownInstance);
    }

    /**
     * @internal Used by SafeDefaultResolver (depth>0, fabricating an
     * intersection-typed return) and by for() itself (depth=0, a direct
     * multi-target double).
     *
     * @param  list<string>  $targets
     */
    public static function fabricateIntersection(array $targets, int $depth): object
    {
        $generatedClass = (new ClassGenerator)->generateForIntersection($targets);

        return self::create($generatedClass, implode('&', $targets), $depth);
    }

    private static function create(string $generatedClass, string $target, int $depth, ?object $knownInstance = null): object
    {
        $state = new DoubleState($target, self::deriveLabel($target));

        if ($depth > 0) {
            $state->markFabricated($depth);
        }

        if ($knownInstance !== null) {
            $state->rememberRealInstance($knownInstance);
        }

        $instance = $generatedClass::__td_instantiate();

        self::states()[$instance] = $state;

        if (self::$autoVerifyArmed) {
            self::$pending[] = $state;
        }

        return $instance;
    }

    /**
     * @internal Used only by DoubleControlMethods::verify() — this static
     * method exists only because the verification logic needs access to the
     * private double->state map, which that trait can't reach directly.
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
     * @internal Used only by VerifiesDoubles's #[Before] hook. Idempotent —
     * safe to call every test — and resets both lists fresh, so a prior test
     * that somehow skipped its own #[After] (e.g. a fatal error mid-test)
     * can't leak stale entries into this one.
     */
    public static function armAutoVerify(): void
    {
        self::$autoVerifyArmed = true;
        self::$pending = [];
        self::$pendingReceived = [];
    }

    /**
     * @internal Used only by VerifiesDoubles's #[After] hook. Both lists are
     * drained up front, before iterating, so a failure partway through never
     * leaves stale entries to leak into whichever test's #[After] runs next.
     */
    public static function verifyAll(): void
    {
        $pending = self::$pending;
        self::$pending = [];

        $pendingReceived = self::$pendingReceived;
        self::$pendingReceived = [];

        foreach ($pending as $state) {
            self::verifyState($state);
        }

        foreach ($pendingReceived as $assertion) {
            $assertion->check();
        }
    }

    /**
     * @internal
     */
    public static function stateFor(object $double): DoubleState
    {
        $state = self::states()[$double] ?? null;

        if ($state === null) {
            throw new \LogicException('Object is not a `TestDouble`-generated double.');
        }

        return $state;
    }

    /**
     * @internal
     */
    public static function registerExpectation(object $double, string $method, bool $required): MethodExpectation
    {
        $state = self::stateFor($double);

        self::assertConfigurable($state, $method);

        $expectation = new MethodExpectation($method, $required);
        $state->registerExpectation($expectation);

        return $expectation;
    }

    /**
     * @internal Used only by DoubleControlMethods::received(). Validates the
     * method name exactly like registerExpectation() does, but never
     * registers anything on DoubleState — the returned ReceivedAssertion
     * checks already-recorded calls, it never participates in live matching.
     */
    public static function received(object $double, string $method): ReceivedAssertion
    {
        $state = self::stateFor($double);

        self::assertConfigurable($state, $method);

        $assertion = new ReceivedAssertion($state, $method);

        if (self::$autoVerifyArmed) {
            self::$pendingReceived[] = $assertion;
        }

        return $assertion;
    }

    /**
     * @internal Used only by DoubleControlMethods::unused(). Unlike
     * received(), there's no fluent chain to wait on — "no calls at all" is
     * a complete assertion on its own, so this checks immediately, the same
     * way verify() does, rather than deferring to __destruct()/verifyAll().
     */
    public static function unused(object $double): void
    {
        $state = self::stateFor($double);
        $calls = $state->calls();

        if ($calls === []) {
            return;
        }

        throw ExceptionFactory::unusedAssertion(
            $state->label(),
            array_map(
                static fn (array $call): string => sprintf('%s(%s)', $call['method'], ArgumentFormatter::describe($call['arguments'])),
                $calls,
            ),
            $state->isFabricated(),
        );
    }

    /**
     * Shared by registerExpectation() and received(): both need the same
     * three checks before they can do anything with $method — that it
     * exists, that it isn't static, and that it isn't magic.
     */
    private static function assertConfigurable(DoubleState $state, string $method): void
    {
        $declaringCandidate = $state->declaringCandidate($method);

        if ($declaringCandidate === null) {
            throw new UnknownMethodException(
                $state->target(),
                $method,
                $state->isFabricated(),
                DidYouMean::suggest($method, $state->declarableMethodNames()),
            );
        }

        if ($state->isStatic($declaringCandidate, $method)) {
            throw new StaticMethodException($state->target(), $method, $state->isFabricated());
        }

        if (str_starts_with($method, '__')) {
            throw new MagicMethodException($state->target(), $method, $state->isFabricated());
        }
    }

    private static function states(): \WeakMap
    {
        return self::$states ??= new \WeakMap;
    }

    /**
     * $target may be several "&"-joined names for an intersection double —
     * each candidate's short name is derived independently and rejoined with
     * "&" (e.g. "Fillable&Sized"), not derived from the joined string
     * itself, which would collapse to just the last candidate's name.
     */
    private static function deriveLabel(string $target): string
    {
        return implode('&', array_map(self::deriveShortName(...), explode('&', $target)));
    }

    private static function deriveShortName(string $target): string
    {
        $position = strrpos($target, '\\');

        return $position === false ? $target : substr($target, $position + 1);
    }
}
