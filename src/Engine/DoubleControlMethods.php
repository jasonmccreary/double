<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

/**
 * @internal
 *
 * Mixed into every generated double by ClassGenerator. These five public
 * methods (expects, allows, strict, passthru, received) are the reserved
 * control API described in ARCHITECTURE.md's "Class surface area" section
 * — real instance methods, deliberately not engineered to zero collision
 * risk. ClassGenerator's collision check runs before a double using this
 * trait is ever generated.
 *
 * expects()/allows()/strict()/passthru() are fully implemented as of M4.
 * received() is a reserved name with a real method (satisfying the "these
 * are real, callable methods" contract) but still throws — its spy-style
 * assertions aren't scoped to any milestone yet.
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

    public function received(string $method): mixed
    {
        throw new \LogicException(
            'received() spy-style assertions are not implemented yet — see ARCHITECTURE.md\'s roadmap.',
        );
    }
}
