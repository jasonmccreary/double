<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\TestDouble;

/**
 * @internal
 *
 * The one safe-default-by-return-type resolver ARCHITECTURE.md calls for
 * (see "Sensible defaults" and "Modes: Loose, Strict, Passthru") — used both
 * by Loose mode's unmatched-call fallback and by any matched expectation
 * missing an explicit ->returns()/->throws()/->resolves(), via the same
 * resolveForMethod() entry point (see ProxyBehavior).
 *
 * A non-nullable return typed as `self`, `static`, or literally the same
 * class/interface that declares the method (e.g. NodeInterface::next():
 * NodeInterface) always resolves to the current double itself — the third
 * case is folded into the same rule as `self` rather than kept distinct.
 * PHP 8.5 changed ReflectionNamedType::getName() to resolve a `self` return
 * type to its actual class/interface name, making it reflectively
 * indistinguishable from an explicit same-named return type (confirmed on
 * PHP 8.5.8: both report `==`-equal ReflectionNamedType values). Treating
 * the two the same everywhere — rather than trying to recover a distinction
 * PHP itself no longer exposes — keeps this resolver's behavior identical
 * across every supported PHP version instead of varying with the running
 * version's reflection quirks. This self/static path never fabricates and
 * is not subject to the fabrication limit below — a self-referential API
 * (like NodeInterface::next()) can be called any number of times without
 * ever hitting it.
 *
 * Recursive fabrication for a non-nullable class/interface return whose name
 * does *not* match the declaring class is a genuine hard limit, enforced at
 * MAX_FABRICATION_DEPTH (default **1** — one safely-typed stand-in fabricated
 * for free, matching the single free hop Mockery's own shouldIgnoreMissing()
 * fallback gets before it stops being type-aware). This is deliberately not
 * "configurable" despite an earlier draft of ARCHITECTURE.md describing it
 * that way — there is no constructor/verb that exposes it, and it is not
 * planned as one; see ARCHITECTURE.md's "Guardrails on fabrication" for why
 * a hard, identically-enforced default was chosen over a per-double knob.
 *
 * Past the limit, null is NOT a viable fallback the way it is for every
 * other row of the safe-default table: the generated method's return type is
 * non-nullable, so PHP itself throws a TypeError the instant `null` crosses
 * that boundary. Two outcomes are possible once the limit is reached:
 *
 * - If the current double already satisfies the required type (a genuine
 *   cycle — the fabricated object needs to return something of a type it
 *   already is), it's reused directly, closing the cycle with no new object
 *   and no error.
 * - Otherwise — a deep, non-cyclic chain of distinct fabricated types — this
 *   resolver throws FabricationLimitExceededException rather than
 *   fabricating further. An earlier version of this resolver fabricated one
 *   level past the limit "anyway" on the reasoning that an honestly-typed
 *   value beats a cap enforced by crashing; in practice that made the limit
 *   not actually a limit; a sufficiently deep unconfigured call chain would
 *   keep fabricating indefinitely, silently, with a fresh eval()'d class per
 *   hop (ClassGenerator does not cache generated classes — see its own
 *   docblock). That is worse than a bare TypeError, not better: it is a
 *   silent, unbounded cost with no stack trace pointing at the real gap in
 *   test setup. A clear, named, immediately-thrown exception — the same
 *   "explain what to do about it" standard every other diagnostic in this
 *   library is held to — is the correct failure mode here, not silence.
 */
final class SafeDefaultResolver
{
    private const MAX_FABRICATION_DEPTH = 1;

    public static function resolveForMethod(DoubleState $state, string $method, object $double): mixed
    {
        $declaringTarget = $state->declaringCandidate($method);

        $reflectionMethod = $declaringTarget !== null ? new \ReflectionMethod($declaringTarget, $method) : null;

        return self::resolve(
            $reflectionMethod?->getReturnType(),
            $double,
            $state->fabricationDepth(),
            $reflectionMethod?->getDeclaringClass()->getName(),
            $state->label(),
            $method,
        );
    }

    private static function resolve(?\ReflectionType $type, object $double, int $depth, ?string $declaringClass, string $label, string $method): mixed
    {
        if ($type === null || $type->allowsNull()) {
            return null;
        }

        if ($type instanceof \ReflectionUnionType) {
            return self::resolveUnion($type, $double, $depth, $declaringClass, $label, $method);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return self::resolveIntersection($type, $double, $depth, $label, $method);
        }

        return self::resolveNamed($type, $double, $depth, $declaringClass, $label, $method);
    }

    /**
     * "First branch that resolves cleanly; prefer null if present" — a
     * member literally named "null" always wins, otherwise the first member
     * is resolved the normal way. "First" here means first as PHP's own
     * ReflectionUnionType::getTypes() returns it — PHP does not preserve
     * source declaration order for a union's members (e.g. `int|string` is
     * reflected back out as `string` then `int`), so there is no other
     * order available to prefer.
     */
    private static function resolveUnion(\ReflectionUnionType $type, object $double, int $depth, ?string $declaringClass, string $label, string $method): mixed
    {
        foreach ($type->getTypes() as $member) {
            if ($member instanceof \ReflectionNamedType && strtolower($member->getName()) === 'null') {
                return null;
            }
        }

        $first = $type->getTypes()[0];

        return $first instanceof \ReflectionIntersectionType
            ? self::resolveIntersection($first, $double, $depth, $label, $method)
            : self::resolveNamed($first, $double, $depth, $declaringClass, $label, $method);
    }

    private static function resolveIntersection(\ReflectionIntersectionType $type, object $double, int $depth, string $label, string $method): mixed
    {
        $names = array_map(
            static fn (\ReflectionType $member): string => (string) $member,
            $type->getTypes(),
        );

        if ($depth >= self::MAX_FABRICATION_DEPTH) {
            if (self::satisfies($double, $names)) {
                return $double;
            }

            throw self::limitExceeded($label, $method, implode('&', $names));
        }

        return TestDouble::fabricateIntersection($names, $depth + 1);
    }

    private static function resolveNamed(\ReflectionNamedType $type, object $double, int $depth, ?string $declaringClass, string $label, string $method): mixed
    {
        $name = $type->getName();
        $lower = strtolower($name);

        if ($type->isBuiltin()) {
            return match ($lower) {
                'bool' => false,
                'int' => 0,
                'float' => 0.0,
                'string' => '',
                'array', 'iterable' => [],
                // 'object', 'callable', 'false', 'true', 'never' aren't in
                // ARCHITECTURE.md's safe-default table — null is a documented
                // best-effort gap for these. void/mixed/null never reach here
                // (already handled by the allowsNull() check above).
                default => null,
            };
        }

        if (in_array($lower, ['self', 'static'], true)
            || ($declaringClass !== null && $lower === strtolower(ltrim($declaringClass, '\\')))) {
            return $double;
        }

        if (enum_exists($name)) {
            return (new \ReflectionEnum($name))->getCases()[0]->getValue();
        }

        if ($depth >= self::MAX_FABRICATION_DEPTH) {
            if (self::satisfies($double, [$name])) {
                return $double;
            }

            throw self::limitExceeded($label, $method, $name);
        }

        return TestDouble::fabricate($name, $depth + 1);
    }

    private static function limitExceeded(string $label, string $method, string $returnType): \Throwable
    {
        return ExceptionFactory::fabricationLimitExceeded($label, $method, $returnType, self::MAX_FABRICATION_DEPTH);
    }

    /**
     * @param  list<string>  $names
     */
    private static function satisfies(object $double, array $names): bool
    {
        foreach ($names as $name) {
            if (! $double instanceof $name) {
                return false;
            }
        }

        return true;
    }
}
