<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Double;

/**
 * @internal
 *
 * The one safe-default-by-return-type resolver in the codebase — used both
 * by Loose mode's unmatched-call fallback and by any matched expectation
 * missing an explicit ->returns()/->throws()/->resolves().
 */
final class SafeDefaultResolver
{
    // One safely-typed stand-in fabricated for free before this hard limit kicks
    // in. Deliberately not configurable: no verb exposes it, and a predictable
    // default was judged more valuable than a tunable one.
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
     * A member literally named "null" always wins; otherwise the first
     * member is resolved the normal way.
     */
    private static function resolveUnion(\ReflectionUnionType $type, object $double, int $depth, ?string $declaringClass, string $label, string $method): mixed
    {
        foreach ($type->getTypes() as $member) {
            if ($member instanceof \ReflectionNamedType && strtolower($member->getName()) === 'null') {
                return null;
            }
        }

        // "First" means first as PHP's own getTypes() returns it — PHP doesn't
        // preserve source declaration order for a union (e.g. `int|string` comes
        // back out as `string` then `int`), so there's no other order to prefer.
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
            // Past the limit, null isn't a viable fallback — the return type is
            // non-nullable. Reuse the double directly if it already satisfies the
            // type (a genuine cycle closing on itself); otherwise this is a deep,
            // non-cyclic chain, so throw rather than fabricate further. An earlier
            // version fabricated one level past the limit "anyway", but that just
            // meant a sufficiently deep unconfigured chain never actually stopped.
            if (self::satisfies($double, $names)) {
                return $double;
            }

            throw self::limitExceeded($label, $method, implode('&', $names));
        }

        return Double::fabricateIntersection($names, $depth + 1);
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
                // 'object', 'callable', 'false', 'true', 'never' have no safe
                // non-null default — null is a documented best-effort gap
                // for these. void/mixed/null never reach here (already
                // handled by the allowsNull() check above).
                default => null,
            };
        }

        // `self`, `static`, and the literal declaring class/interface name (e.g.
        // NodeInterface::next(): NodeInterface) all fold into one rule here: PHP 8.5
        // changed ReflectionNamedType::getName() to resolve `self` to its actual
        // class name, making it reflectively indistinguishable from an explicit
        // same-named return type (confirmed on 8.5.8: both report `==`-equal
        // ReflectionNamedType values). This path never fabricates or counts against
        // the depth limit below — a self-referential API can be called any number
        // of times without ever hitting it.
        if (in_array($lower, ['self', 'static'], true)
            || ($declaringClass !== null && $lower === strtolower(ltrim($declaringClass, '\\')))) {
            return $double;
        }

        if (enum_exists($name)) {
            return (new \ReflectionEnum($name))->getCases()[0]->getValue();
        }

        if ($depth >= self::MAX_FABRICATION_DEPTH) {
            // Same reasoning as resolveIntersection() above.
            if (self::satisfies($double, [$name])) {
                return $double;
            }

            throw self::limitExceeded($label, $method, $name);
        }

        return Double::fabricate($name, $depth + 1);
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
