<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Exceptions\UnknownMethodException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;

/**
 * The public facade. `TestDouble::for()` creates a double; `TestDouble::verify()`
 * is the manual verification call every test runner can use (see
 * ARCHITECTURE.md, "PHPUnit integration" — a framework-specific
 * auto-verify extension is future, additive work, not M1).
 */
final class TestDouble
{
    private static ?\WeakMap $states = null;

    private function __construct() {}

    public static function for(string $target): object
    {
        $generatedClass = (new ClassGenerator)->generate($target);

        $state = new DoubleState($target, self::deriveLabel($target));

        $instance = $generatedClass::__td_instantiate();

        self::states()[$instance] = $state;

        return $instance;
    }

    public static function verify(object $double): void
    {
        $state = self::stateFor($double);
        $unmet = $state->unmetExpectations();

        if ($unmet !== []) {
            throw UnsatisfiedExpectationException::forUnmet(
                $state->label(),
                array_map(static fn (MethodExpectation $expectation): string => $expectation->describe(), $unmet),
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

        if (! method_exists($state->target(), $method)) {
            throw UnknownMethodException::forMethod($state->target(), $method);
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
