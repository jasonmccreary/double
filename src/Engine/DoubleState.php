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
 * wins"), and every call actually observed, regardless of whether it
 * matched anything.
 */
final class DoubleState
{
    /** @var list<MethodExpectation> */
    private array $expectations = [];

    /** @var list<array{method: string, arguments: array}> */
    private array $calls = [];

    private ?Mode $mode = null;

    private ?object $passthruTarget = null;

    // A real instance supplied directly to TestDouble::for($instance). Remembered
    // independent of mode, so a later ->passthru() with no argument can reuse it
    // instead of auto-instantiating a fresh one — kept separate from
    // $passthruTarget since knowing about a real instance and actually being in
    // Passthru mode are two different things.
    private ?object $knownInstance = null;

    private int $fabricationDepth = 0;

    /**
     * The furthest slot reached so far by an inOrder()-marked call — see
     * orderedExpectations(). 0 is a safe starting sentinel: the first inOrder()-marked
     * expectation's own slot is always index 0, and comparing a slot against
     * itself never counts as a regression.
     */
    private int $orderCursor = 0;

    public function __construct(
        private readonly string $target,
        private readonly string $label,
    ) {}

    public function target(): string
    {
        return $this->target;
    }

    /**
     * Almost always a single-element list. An intersection-typed fabrication
     * stores its constituent interfaces joined with "&" in $target for
     * display — PHP names can never contain "&", so splitting on it is safe.
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

    /**
     * Whether $method, as declared on $declaringCandidate, is static. Takes
     * $declaringCandidate as a parameter since callers already have it in
     * hand from declaringCandidate() before they'd need this.
     */
    public function isStatic(string $declaringCandidate, string $method): bool
    {
        return (new \ReflectionMethod($declaringCandidate, $method))->isStatic();
    }

    /**
     * Every method name method_exists() would find on any target candidate,
     * for UnknownMethodException's "did you mean" suggestion.
     *
     * @return list<string>
     */
    public function declarableMethodNames(): array
    {
        $names = [];

        foreach ($this->targetCandidates() as $candidate) {
            // Reflection's own getMethods(), not get_class_methods() — the latter
            // only sees public methods from outside the declaring class, and this
            // should stay exactly as permissive as declaringCandidate() about visibility.
            foreach ((new \ReflectionClass($candidate))->getMethods() as $method) {
                $names[$method->getName()] = true;
            }
        }

        return array_keys($names);
    }

    public function label(): string
    {
        return $this->label;
    }

    /**
     * Loose is the architected default — reachable only implicitly, since
     * there's deliberately no ->loose() verb.
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
     * target together, so the two can never end up out of sync.
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
     * @internal used only by TestDouble::create()
     */
    public function rememberRealInstance(object $instance): void
    {
        $this->knownInstance = $instance;
    }

    public function knownInstance(): ?object
    {
        return $this->knownInstance;
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

    /**
     * Every inOrder()-marked expectation registered on this double, in
     * registration order. An expectation's position in this list is its slot for call-order
     * enforcement (see ProxyBehavior); no separate slot-numbering
     * bookkeeping is needed since $expectations is already
     * registration-ordered.
     *
     * @return list<MethodExpectation>
     */
    public function orderedExpectations(): array
    {
        return array_values(array_filter(
            $this->expectations,
            static fn (MethodExpectation $expectation): bool => $expectation->isOrdered(),
        ));
    }

    public function orderCursor(): int
    {
        return $this->orderCursor;
    }

    public function advanceOrderCursor(int $slot): void
    {
        $this->orderCursor = $slot;
    }
}
