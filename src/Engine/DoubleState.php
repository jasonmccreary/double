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

    private ?object $passthruTarget = null;

    private int $fabricationDepth = 0;

    public function __construct(
        private readonly string $target,
        private readonly string $label,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    /**
     * Almost always a single-element list ([target()]). An intersection-typed
     * fabrication (see TestDouble::fabricateIntersection()) stores its
     * constituent interfaces joined with "&" in $target purely for display —
     * PHP class/interface names can never contain "&", so splitting on it is
     * unambiguous.
     *
     * @return list<string>
     */
    public function targetCandidates(): array
    {
        return explode('&', $this->target);
    }

    /**
     * The first target candidate (see targetCandidates()) that declares the
     * given method, or null if none does. Used by callers that need to
     * reflect a method (e.g. SafeDefaultResolver) or check it exists
     * (TestDouble::registerExpectation).
     */
    public function declaringCandidate(string $method): ?string
    {
        foreach ($this->targetCandidates() as $candidate) {
            if (method_exists($candidate, $method)) {
                return $candidate;
            }
        }

        return null;
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * Loose is the architected default (see ARCHITECTURE.md's "Sensible
     * defaults" table) — reachable only implicitly, since there's
     * deliberately no ->loose() verb (see ARCHITECTURE.md, "Modes: Loose,
     * Strict, Passthru").
     */
    public function mode(): Mode
    {
        return $this->mode ?? Mode::Loose;
    }

    public function setMode(Mode $mode): void
    {
        if ($this->mode !== null) {
            throw new ModeConfigurationException($this->label, $this->mode->name, $mode->name, $this->isFabricated());
        }

        $this->mode = $mode;
    }

    /**
     * ->passthru($realInstance) sets the mode and stores the delegation
     * target together, so the two can never end up out of sync (see
     * ARCHITECTURE.md, "Passthru").
     */
    public function configurePassthru(object $realInstance): void
    {
        $this->setMode(Mode::Passthru);
        $this->passthruTarget = $realInstance;
    }

    /**
     * Only ever called from the Mode::Passthru fallback branch, which is
     * unreachable unless configurePassthru() already ran.
     */
    public function passthruTarget(): object
    {
        /** @var object $target */
        $target = $this->passthruTarget;

        return $target;
    }

    /**
     * @internal used only by TestDouble::fabricate()/fabricateIntersection()
     */
    public function markFabricated(int $depth): void
    {
        $this->fabricationDepth = $depth;
    }

    public function fabricationDepth(): int
    {
        return $this->fabricationDepth;
    }

    public function isFabricated(): bool
    {
        return $this->fabricationDepth > 0;
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
