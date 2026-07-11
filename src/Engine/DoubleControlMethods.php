<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\TestDouble;

/**
 * @internal
 *
 * Mixed into every generated double by ClassGenerator. These six public
 * methods (expects, allows, strict, passthru, received, verify) are the
 * reserved control API described in ARCHITECTURE.md's "Class surface area"
 * section — real instance methods, deliberately not engineered to zero
 * collision risk. ClassGenerator's collision check runs before a double
 * using this trait is ever generated.
 *
 * expects()/allows()/strict()/passthru()/verify()/received() are all fully
 * implemented as of M4.
 *
 * verify() and received() both delegate to a same-named TestDouble static
 * method, which holds the actual implementation (it needs access to the
 * private static double->state map). Those TestDouble methods are marked
 * internal, not a second public entry point — the no-alias policy in
 * CONTRIBUTING.md means the double's own verify()/received() are the only
 * supported way to call either.
 *
 * received() itself just returns a ReceivedAssertion — see its docblock for
 * why the actual spy-style check happens in that object's __destruct()
 * rather than here.
 */
trait DoubleControlMethods
{
    /** @internal */
    public static function __td_instantiate(): static
    {
        return (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }

    public function expects(string $method): MethodExpectation
    {
        return TestDouble::registerExpectation($this, $method, required: true);
    }

    public function allows(string $method): MethodExpectation
    {
        return TestDouble::registerExpectation($this, $method, required: false);
    }

    public function strict(): static
    {
        TestDouble::stateFor($this)->setMode(Mode::Strict);

        return $this;
    }

    public function passthru(?object $realInstance = null): static
    {
        $state = TestDouble::stateFor($this);
        $realInstance ??= PassthruInstantiator::autoInstantiate($state->target());

        $state->configurePassthru($realInstance);

        return $this;
    }

    public function received(string $method): ReceivedAssertion
    {
        return TestDouble::received($this, $method);
    }

    public function verify(): void
    {
        TestDouble::verify($this);
    }
}
