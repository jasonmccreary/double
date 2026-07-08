<?php

declare(strict_types=1);

namespace TestDouble\Engine;

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
 * M1 implements expects()/allows()/strict() fully. passthru() and
 * received() are reserved names with real methods (satisfying the
 * "these are real, callable methods" contract) but throw — both are
 * out of the M1 component list in ARCHITECTURE.md's roadmap (Passthru is
 * explicit M4 scope; received()'s spy-style assertions aren't scoped to
 * any milestone yet).
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
        throw new \LogicException(
            'Passthru mode is not implemented yet. It ships in M4 — see ARCHITECTURE.md\'s roadmap.',
        );
    }

    public function received(string $method): mixed
    {
        throw new \LogicException(
            'received() spy-style assertions are not implemented yet in M1 — see ARCHITECTURE.md\'s roadmap.',
        );
    }
}
