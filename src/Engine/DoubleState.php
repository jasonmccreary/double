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

    /**
     * A real instance supplied directly to TestDouble::for($instance) — see
     * its docblock. Remembered here independent of mode, so a later
     * ->passthru() with no argument can use it instead of needing to
     * auto-instantiate a fresh one. Deliberately a separate field from
     * $passthruTarget: knowing about a real instance and actually being in
     * Passthru mode are two different things — this can be set at creation
     * time, long before (or even if never) ->passthru() is called at all.
     */
    private ?object $knownInstance = null;

    private int $fabricationDepth = 0;

    /**
     * The furthest slot reached so far by an inOrder()-marked call — see
     * orderedExpectations() and ARCHITECTURE.md, "Call-order enforcement".
     * 0 is a safe starting sentinel: the first inOrder()-marked
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

    /**
     * Whether $method, as declared on $declaringCandidate, is static —
     * callers already have $declaringCandidate in hand from
     * declaringCandidate() before they'd ever need this, so it's taken as a
     * parameter rather than re-deriving it here. Used by
     * TestDouble::registerExpectation()/received() to reject configuring a
     * static method the same way an unknown one is rejected — see
     * StaticMethodException's own docblock for why a static method can
     * never actually be intercepted regardless.
     */
    public function isStatic(string $declaringCandidate, string $method): bool
    {
        return (new \ReflectionMethod($declaringCandidate, $method))->isStatic();
    }

    /**
     * Every method name method_exists() would find on any target candidate —
     * the same existence check declaringCandidate() uses — for
     * UnknownMethodException's "did you mean" suggestion. Reflection's own
     * getMethods() (not get_class_methods(), which only sees public methods
     * from outside the declaring class) so this stays exactly as permissive
     * as declaringCandidate() about visibility.
     *
     * @return list<string>
     */
    public function declarableMethodNames(): array
    {
        $names = [];

        foreach ($this->targetCandidates() as $candidate) {
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
     * registration order — see ARCHITECTURE.md, "Call-order enforcement".
     * An expectation's position in this list is its slot for call-order
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
