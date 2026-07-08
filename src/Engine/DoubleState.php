<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Exceptions\ModeConfigurationException;

/**
 * @internal
 *
 * Holds everything about one double: the target it was created for, its
 * display label, its mode, every expectation registered against it (in
 * registration order, since matching is "last-registered-that-matches
 * wins" — see ARCHITECTURE.md), and every call actually observed,
 * regardless of whether it matched anything.
 */
final class DoubleState
{
    /** @var list<MethodExpectation> */
    private array $expectations = [];

    /** @var list<array{method: string, arguments: array}> */
    private array $calls = [];

    private ?Mode $mode = null;

    public function __construct(
        private readonly string $target,
        private readonly string $label,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * M1 stand-in: Loose is the architected default (see ARCHITECTURE.md's
     * "Sensible defaults" table) but isn't implemented until M4, so an
     * unset mode currently behaves as Strict. This is the only place that
     * decision lives — flip the `??` fallback to Mode::Loose when M4 lands.
     */
    public function mode(): Mode
    {
        return $this->mode ?? Mode::Strict;
    }

    public function setMode(Mode $mode): void
    {
        if ($this->mode !== null) {
            throw ModeConfigurationException::alreadyConfigured($this->label, $this->mode->name, $mode->name);
        }

        $this->mode = $mode;
    }

    public function registerExpectation(MethodExpectation $expectation): void
    {
        $this->expectations[] = $expectation;
    }

    /**
     * @return list<MethodExpectation> in registration order
     */
    public function expectationsFor(string $method): array
    {
        return array_values(array_filter(
            $this->expectations,
            static fn (MethodExpectation $expectation): bool => $expectation->method() === $method,
        ));
    }

    public function recordCall(string $method, array $arguments): void
    {
        $this->calls[] = ['method' => $method, 'arguments' => $arguments];
    }

    /**
     * @return list<array>
     */
    public function callsFor(string $method): array
    {
        return array_values(array_map(
            static fn (array $call): array => $call['arguments'],
            array_filter($this->calls, static fn (array $call): bool => $call['method'] === $method),
        ));
    }

    /**
     * @return list<MethodExpectation>
     */
    public function unmetExpectations(): array
    {
        return array_values(array_filter(
            $this->expectations,
            static fn (MethodExpectation $expectation): bool => ! $expectation->isSatisfied(),
        ));
    }
}
