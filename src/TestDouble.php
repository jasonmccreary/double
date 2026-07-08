<?php

declare(strict_types=1);

namespace JMac\Testing;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Engine\ArgumentFormatter;
use JMac\Testing\Engine\ClassGenerator;
use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Exceptions\UnknownMethodException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;

/**
 * The public facade and the library's sole entry point — deliberately a
 * top-level class (`JMac\Testing\TestDouble`, not nested under `Engine\`)
 * since it's the one class every consumer touches directly; everything it
 * delegates to (`ClassGenerator`, `ProxyBehavior`, `DoubleState`,
 * `MethodExpectation`) stays internal under Engine. `TestDouble::for()`
 * creates a double; `TestDouble::verify()` is the manual verification call
 * every test runner can use (see ARCHITECTURE.md, "PHPUnit integration" —
 * a framework-specific auto-verify extension is future, additive work, not
 * M1).
 */
final class TestDouble
{
    private static ?\WeakMap $states = null;

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

        return $instance;
    }

    public static function verify(object $double): void
    {
        $state = self::stateFor($double);
        $unmet = $state->unmetExpectations();

        if ($unmet !== []) {
            throw new UnsatisfiedExpectationException(
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

        $declared = false;
        foreach ($state->targetCandidates() as $candidate) {
            if (method_exists($candidate, $method)) {
                $declared = true;
                break;
            }
        }

        if (! $declared) {
            throw new UnknownMethodException($state->target(), $method, $state->isFabricated());
        }

        $expectation = new MethodExpectation($method, $required);
        $state->registerExpectation($expectation);

        return $expectation;
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
