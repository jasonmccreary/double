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
 * missing an explicit ->returns()/->throws()/->returnsUsing(), via the same
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
 * version's reflection quirks.
 *
 * Recursive fabrication for a non-nullable class/interface return whose name
 * does *not* match the declaring class is still capped at
 * MAX_FABRICATION_DEPTH (ARCHITECTURE.md: "proposed default 2, configurable
 * — not yet validated against real domain object graphs; treat as a
 * starting point to tune, not a settled constant") to stop an unbounded
 * chain from recursing forever.
 *
 * Past the cap, null is NOT a viable fallback the way it is for every other
 * row of the safe-default table: the generated method's return type is
 * non-nullable, so PHP itself throws a TypeError the instant `null` crosses
 * that boundary — this resolver can't paper over that with a "safe" value
 * that isn't actually one. Instead, at the cap, the current double is
 * reused as the return value whenever it already satisfies the required
 * type — closing the cycle instead of fabricating forever. If it doesn't
 * satisfy the type (a deep but non-cyclic graph), fabrication proceeds one
 * level further anyway, since an honestly-typed value beats a cap enforced
 * by crashing.
 */
final class SafeDefaultResolver
{
    private const MAX_FABRICATION_DEPTH = 2;

    public static function resolveForMethod(DoubleState $state, string $method, object $double): mixed
    {
        $declaringTarget = null;
        foreach ($state->targetCandidates() as $candidate) {
            if (method_exists($candidate, $method)) {
                $declaringTarget = $candidate;
                break;
            }
        }

        $reflectionMethod = $declaringTarget !== null ? new \ReflectionMethod($declaringTarget, $method) : null;

        return self::resolve(
            $reflectionMethod?->getReturnType(),
            $double,
            $state->fabricationDepth(),
            $reflectionMethod?->getDeclaringClass()->getName(),
        );
    }

    private static function resolve(?\ReflectionType $type, object $double, int $depth, ?string $declaringClass): mixed
    {
        if ($type === null || $type->allowsNull()) {
            return null;
        }

        if ($type instanceof \ReflectionUnionType) {
            return self::resolveUnion($type, $double, $depth, $declaringClass);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return self::resolveIntersection($type, $double, $depth);
        }

        return self::resolveNamed($type, $double, $depth, $declaringClass);
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
    private static function resolveUnion(\ReflectionUnionType $type, object $double, int $depth, ?string $declaringClass): mixed
    {
        foreach ($type->getTypes() as $member) {
            if ($member instanceof \ReflectionNamedType && strtolower($member->getName()) === 'null') {
                return null;
            }
        }

        $first = $type->getTypes()[0];

        return $first instanceof \ReflectionIntersectionType
            ? self::resolveIntersection($first, $double, $depth)
            : self::resolveNamed($first, $double, $depth, $declaringClass);
    }

    private static function resolveIntersection(\ReflectionIntersectionType $type, object $double, int $depth): mixed
    {
        $names = array_map(
            static fn (\ReflectionType $member): string => (string) $member,
            $type->getTypes(),
        );

        if ($depth >= self::MAX_FABRICATION_DEPTH) {
            if (self::satisfies($double, $names)) {
                return $double;
            }
        }

        return TestDouble::fabricateIntersection($names, $depth + 1);
    }

    private static function resolveNamed(\ReflectionNamedType $type, object $double, int $depth, ?string $declaringClass): mixed
    {
        $name = $type->getName();
        $lower = strtolower($name);

        if (! $type->isBuiltin()) {
            if (in_array($lower, ['self', 'static'], true)
                || ($declaringClass !== null && $lower === strtolower(ltrim($declaringClass, '\\')))) {
                return $double;
            }

            if (enum_exists($name)) {
                return (new \ReflectionEnum($name))->getCases()[0]->getValue();
            }

            if ($depth >= self::MAX_FABRICATION_DEPTH && self::satisfies($double, [$name])) {
                return $double;
            }

            return TestDouble::fabricate($name, $depth + 1);
        }

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
