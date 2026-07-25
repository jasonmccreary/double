<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Exceptions\InvalidDoubleTargetException;
use JMac\Testing\Exceptions\ReservedNameCollisionException;
use JMac\Testing\TestDoubleInterface;

/**
 * @internal
 *
 * Reflects a target class/interface and eval()s a new class that either
 * extends or implements it, overriding every overridable method to funnel
 * through ProxyBehavior::intercept(). Uses the same eval() technique as
 * Mockery/Prophecy, and caches the generated class per distinct
 * target combination (module-static, same lifetime as $counter below),
 * mirroring Mockery's own CachingGenerator: a second call to
 * generate()/generateForIntersection() for the same target(s) returns the
 * already-declared class name instead of eval()ing a fresh one. This is
 * what keeps repeatedly doubling the same target (a data provider, a
 * tight loop, a fuzz test) from costing unbounded process memory —
 * uncached, this cost ~1KB-1.5KB of process memory per public method on
 * the target, per call, permanently, for the life of the process.
 *
 * Signature reconstruction always derives parameter/return type text from
 * Reflection's own type objects (never from re-parsing source), which is
 * what guarantees ClassGenerator never emits an implicit-nullable
 * parameter: PHP's ReflectionNamedType::__toString() always includes the
 * leading "?" whenever allowsNull() is true, regardless of whether the
 * original source spelled that nullability as `?Type` or as the
 * deprecated implicit `Type $x = null` form.
 *
 * Magic methods (__toString, __invoke, __call, even __construct/__destruct
 * — anything starting with "__") are never overridden either, but that's a
 * deliberate rejection rather than an open gap. A concrete target's magic
 * method is simply inherited unoverridden
 * (harmless — real code, just not interceptable, same as a concrete static
 * method); an abstract one (always true on an interface, possibly on an
 * abstract class too) would leave the generated class not actually
 * implementing it, which assertNoAbstractMagicMethods() rejects before this
 * generator ever runs eval().
 *
 * Properties (including PHP 8.4+ hooked ones) are never reasoned about at
 * all — this generator only ever overrides methods. A concrete target's
 * hooked property is simply inherited unchanged (harmless — real code,
 * just not interceptable through this library's method-based API, which
 * has no property-configuration verb to begin with); an interface
 * requiring one (abstract) would leave the generated class not actually
 * implementing it, which assertNoAbstractPropertyHooks() rejects before
 * this generator ever runs eval().
 */
final class ClassGenerator
{
    private const RESERVED_METHODS = ['expects', 'allows', 'strict', 'passthru', 'received', 'verify'];

    private static int $counter = 0;

    /**
     * @var array<string, string> cache key (see cacheKey()) => already-declared generated class name
     */
    private static array $cache = [];

    public function generate(string $target): string
    {
        if (! class_exists($target) && ! interface_exists($target)) {
            throw InvalidDoubleTargetException::doesNotExist($target);
        }

        $reflection = new \ReflectionClass($target);

        if ($reflection->isFinal()) {
            throw InvalidDoubleTargetException::isFinal($target);
        }

        $keyword = $reflection->isInterface() ? 'implements' : 'extends';

        return $this->generateFromReflections([$reflection], [$target], $keyword);
    }

    /**
     * @internal used only by SafeDefaultResolver (fabricating an
     * intersection-typed return) and TestDouble::for() (a direct multi-target
     * double, e.g. TestDouble::for(Foo::class, Bar::class)). Intersection
     * members are always interfaces in PHP, so — unlike generate() — this
     * never needs the extends-vs-implements branching a single class/interface
     * target requires; it validates every target actually is one instead.
     *
     * @param  list<string>  $targets
     */
    public function generateForIntersection(array $targets): string
    {
        $this->assertValidIntersectionTargets($targets);

        $reflections = array_map(
            static fn (string $target): \ReflectionClass => new \ReflectionClass($target),
            $targets,
        );

        return $this->generateFromReflections($reflections, $targets, 'implements');
    }

    /**
     * @param  list<string>  $targets
     */
    private function assertValidIntersectionTargets(array $targets): void
    {
        $seen = [];

        foreach ($targets as $target) {
            if (isset($seen[$target])) {
                throw InvalidDoubleTargetException::duplicateTarget($target);
            }

            $seen[$target] = true;

            if (interface_exists($target)) {
                continue;
            }

            throw class_exists($target)
                ? InvalidDoubleTargetException::mustBeInterface($target)
                : InvalidDoubleTargetException::doesNotExist($target);
        }
    }

    /**
     * @param  list<\ReflectionClass>  $reflections
     * @param  list<string>  $targets
     */
    private function generateFromReflections(array $reflections, array $targets, string $keyword): string
    {
        $cacheKey = $this->cacheKey($targets);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $this->assertNoReservedNameCollisions($targets, $reflections);
        $this->assertNoAbstractStaticMethods($targets, $reflections);
        $this->assertNoAbstractMagicMethods($targets, $reflections);
        $this->assertNoAbstractPropertyHooks($targets, $reflections);

        $className = $this->generateClassName($reflections);

        eval($this->buildSource($className, $keyword, $targets, $reflections));

        return self::$cache[$cacheKey] = $className;
    }

    /**
     * Nothing buildSource() reads varies per call beyond the target list
     * itself (no per-double method-exclusion list exists yet — see this
     * class's own docblock) — so the sorted target list is a sufficient
     * cache key today. Sorted rather than positional: generateForIntersection()
     * merges overridable methods across targets without regard to argument
     * order (PHP's own intersection-type rules already require compatible
     * signatures across constituents), so `[A, B]` and `[B, A]` are the same
     * request and should share one cached class rather than generating twice.
     *
     * @param  list<string>  $targets
     */
    private function cacheKey(array $targets): string
    {
        $sorted = $targets;
        sort($sorted);

        return implode('&', $sorted);
    }

    /**
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoReservedNameCollisions(array $targets, array $reflections): void
    {
        $declared = [];

        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $declared[] = $method->getName();
            }
        }

        $collisions = array_values(array_intersect(self::RESERVED_METHODS, $declared));

        if ($collisions !== []) {
            throw ReservedNameCollisionException::forCollisions(implode('&', $targets), $collisions);
        }
    }

    /**
     * overridableMethods() below deliberately never overrides a static
     * method (see its own docblock — there's no instance to dispatch
     * through). That's harmless for a concrete, already-implemented static
     * method: the generated subclass just inherits the real one unchanged.
     * It's fatal for an abstract one — always true of every interface
     * method, and possible on an abstract class too — since PHP then
     * requires the generated class to either implement it or be abstract
     * itself, and this generator only ever emits `final class`. Caught
     * here, before eval(), as a normal InvalidDoubleTargetException; left
     * uncaught, PHP raises this as an uncatchable fatal error from inside
     * the eval()'d source instead, which is a far worse failure mode than
     * any exception this library throws on purpose.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoAbstractStaticMethods(array $targets, array $reflections): void
    {
        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                if ($method->isStatic() && $method->isAbstract()) {
                    throw InvalidDoubleTargetException::hasAbstractStaticMethod(implode('&', $targets), $method->getName());
                }
            }
        }
    }

    /**
     * Mirrors assertNoAbstractStaticMethods() above, for the same failure
     * shape and a different reason a method ends up excluded from
     * overriding — see overridableMethods()'s own str_starts_with('__')
     * exclusion. Runs after the static check, so a method that's both
     * static and abstract and magic (__callStatic, in practice) is already
     * caught there first; this only ever needs to catch the non-static
     * remainder.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoAbstractMagicMethods(array $targets, array $reflections): void
    {
        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                if (str_starts_with($method->getName(), '__') && $method->isAbstract()) {
                    throw InvalidDoubleTargetException::hasAbstractMagicMethod(implode('&', $targets), $method->getName());
                }
            }
        }
    }

    /**
     * A third mirror of assertNoAbstractStaticMethods() above, for the same
     * failure shape and yet another reason a member ends up excluded from
     * overriding: `ClassGenerator` never reasons about properties at all
     * (only methods), so a target requiring a hooked property
     * (`public string $name { get; }`, PHP 8.4+) leaves the generated class
     * with the same kind of unimplemented abstract member the static/magic
     * method checks already guard against.
     *
     * ReflectionProperty::isAbstract() (and property hooks generally) don't
     * exist before PHP 8.4 — this library's floor is 8.3 — so the
     * method_exists() guard is load-bearing, not defensive dead code: on
     * 8.3 there's nothing to check, since a property can't be abstract at
     * all yet.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoAbstractPropertyHooks(array $targets, array $reflections): void
    {
        if (! method_exists(\ReflectionProperty::class, 'isAbstract')) {
            return;
        }

        foreach ($reflections as $reflection) {
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED) as $property) {
                if ($property->isAbstract()) {
                    throw InvalidDoubleTargetException::hasAbstractPropertyHook(implode('&', $targets), $property->getName());
                }
            }
        }
    }

    /**
     * @param  list<\ReflectionClass>  $reflections
     */
    private function generateClassName(array $reflections): string
    {
        $short = implode('_', array_map(
            static fn (\ReflectionClass $reflection): string => preg_replace('/[^A-Za-z0-9_]/', '_', $reflection->getShortName()),
            $reflections,
        ));

        return sprintf('JMac\Testing\\Engine\\Generated\\%s_%d', $short, ++self::$counter);
    }

    /**
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function buildSource(string $fqcn, string $keyword, array $targets, array $reflections): string
    {
        $position = strrpos($fqcn, '\\');
        $namespace = substr($fqcn, 0, $position);
        $shortName = substr($fqcn, $position + 1);

        $parents = implode(', ', array_map(
            static fn (string $target): string => '\\'.ltrim($target, '\\'),
            $targets,
        ));

        // Every generated double implements TestDoubleInterface for real, on top of
        // whatever the caller's own target(s) were — not just as a docblock fiction
        // for TestDouble::for()'s @template/@return pairing, see that interface's own
        // docblock for why. A single-class target uses `extends`, so the interface
        // needs its own `implements` clause; an interface target (or several, for an
        // intersection double) already uses `implements`, so it just joins the list.
        $controlInterface = '\\'.ltrim(TestDoubleInterface::class, '\\');
        $inheritance = $keyword === 'extends'
            ? sprintf('extends %s implements %s', $parents, $controlInterface)
            : sprintf('implements %s, %s', $parents, $controlInterface);

        $methods = implode("\n", array_map(
            $this->buildMethod(...),
            $this->overridableMethods($reflections),
        ));

        return sprintf(
            "namespace %s;\n\nfinal class %s %s\n{\n    use \\%s;\n\n%s\n}\n",
            $namespace,
            $shortName,
            $inheritance,
            DoubleControlMethods::class,
            $methods,
        );
    }

    /**
     * Merges overridable methods across every target, keyed by name — the
     * first reflection to declare a given method wins. Only relevant for
     * intersection fabrication (a single-target call only ever has one
     * reflection); PHP's own intersection-type rules already require
     * compatible signatures across constituents, so any occurrence's
     * signature is a valid one to emit.
     *
     * A static method is always excluded: ProxyBehavior::intercept() takes
     * $this and dispatches through it, and a static call has no $this to
     * give it. A concrete static method is simply inherited unoverridden
     * (harmless — it's real code, just not interceptable); an abstract one
     * would leave the generated class not actually implementing it, which
     * assertNoAbstractStaticMethods() rejects before this method ever runs.
     *
     * @param  list<\ReflectionClass>  $reflections
     * @return list<\ReflectionMethod>
     */
    private function overridableMethods(array $reflections): array
    {
        $methods = [];

        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                if (isset($methods[$method->getName()])) {
                    continue;
                }

                if ($method->isFinal()
                    || $method->isStatic()
                    || $method->isConstructor()
                    || $method->isDestructor()
                    || str_starts_with($method->getName(), '__')
                ) {
                    continue;
                }

                $methods[$method->getName()] = $method;
            }
        }

        return array_values($methods);
    }

    private function buildMethod(\ReflectionMethod $method): string
    {
        $name = $method->getName();
        $visibility = $method->isProtected() ? 'protected' : 'public';

        $parameters = implode(', ', array_map(
            $this->buildParameter(...),
            $method->getParameters(),
        ));

        // getReturnType() is null for a method whose only declared return type is
        // "tentative" — the mechanism every internal interface method PHP 8.1+
        // ships with (ArrayAccess::offsetGet(), Countable::count(), Iterator::
        // current(), etc.) uses while its return type is still being phased in as
        // enforced. Falling back to getTentativeReturnType() here is what stops a
        // generated override of e.g. ArrayAccess::offsetExists() from being built
        // with no return type at all — which PHP accepts, but flags as a
        // deprecated incompatibility against the interface's own tentative one.
        $returnType = $method->getReturnType() ?? ($method->hasTentativeReturnType() ? $method->getTentativeReturnType() : null);
        $returnTypeString = $returnType !== null ? $this->stringifyType($returnType) : null;
        $returnDeclaration = $returnTypeString !== null ? ': '.$returnTypeString : '';
        $isVoid = $returnTypeString === 'void';

        $call = sprintf(
            '\\%s::intercept($this, %s, func_get_args())',
            ProxyBehavior::class,
            var_export($name, true),
        );

        // A by-reference-returning method (`function &foo()`) requires the override to
        // be declared with the same leading "&", or PHP rejects it as an incompatible
        // signature — a fatal error at eval() time, the same failure shape the
        // magic-method and static-method checks elsewhere in this class already guard
        // against, just for a third, unrelated reason a signature can mismatch.
        // `return $call;` directly (the ordinary body below) triggers its own separate
        // issue once the method is declared by-reference: PHP allows returning a
        // temporary by reference, but emits "Only variable references should be
        // returned by reference" on every single call, since intercept()'s own result
        // isn't itself a reference. Assigning to a real local variable first, then
        // returning that variable, is what a by-ref return actually needs to be silent.
        $reference = $method->returnsReference() ? '&' : '';
        $body = match (true) {
            $reference !== '' => sprintf("\$__td_result = %s;\n\n        return \$__td_result;", $call),
            $isVoid => sprintf('%s;', $call),
            default => sprintf('return %s;', $call),
        };

        return sprintf(
            "    %s function %s%s(%s)%s\n    {\n        %s\n    }\n",
            $visibility,
            $reference,
            $name,
            $parameters,
            $returnDeclaration,
            $body,
        );
    }

    private function buildParameter(\ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $typeDeclaration = $type !== null ? $this->stringifyType($type).' ' : '';

        $byRef = $parameter->isPassedByReference() ? '&' : '';
        $variadic = $parameter->isVariadic() ? '...' : '';

        $default = '';

        if (! $parameter->isVariadic() && $parameter->isDefaultValueAvailable()) {
            $default = ' = '.($parameter->isDefaultValueConstant()
                ? $this->qualifyConstantName($parameter->getDefaultValueConstantName())
                : var_export($parameter->getDefaultValue(), true));
        }

        return sprintf('%s%s%s$%s%s', $typeDeclaration, $byRef, $variadic, $parameter->getName(), $default);
    }

    /**
     * The generated class lives in its own namespace, so any class-qualified
     * default (an enum case, a ::class constant) must be forced absolute —
     * otherwise PHP resolves it relative to the generated namespace instead
     * of the one the original signature was written in.
     */
    private function qualifyConstantName(string $name): string
    {
        return str_contains($name, '::') && ! str_starts_with($name, '\\') ? '\\'.$name : $name;
    }

    private function stringifyType(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $this->stringifyNamedType($type);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            return $this->stringifyIntersectionType($type);
        }

        // ReflectionUnionType: members are either ReflectionNamedType or,
        // for a member like (A&B)|C, a nested ReflectionIntersectionType.
        return implode('|', array_map(
            fn (\ReflectionType $member): string => $member instanceof \ReflectionIntersectionType
                ? '('.$this->stringifyIntersectionType($member).')'
                : $this->stringifyNamedType($member),
            $type->getTypes(),
        ));
    }

    private function stringifyIntersectionType(\ReflectionIntersectionType $type): string
    {
        return implode('&', array_map($this->stringifyNamedType(...), $type->getTypes()));
    }

    /**
     * Mirrors PHP's own ReflectionNamedType::__toString() (leading "?" iff
     * allowsNull() and the type isn't "mixed"/"null" itself) but additionally
     * forces class/interface names absolute with a leading "\", which
     * __toString() does not do — necessary because the generated class lives
     * in its own namespace, not the target's.
     */
    private function stringifyNamedType(\ReflectionNamedType $type): string
    {
        $name = $type->getName();
        $lower = strtolower($name);

        if (! $type->isBuiltin() && ! in_array($lower, ['self', 'static', 'parent'], true)) {
            $name = '\\'.$name;
        }

        $nullablePrefix = $type->allowsNull() && ! in_array($lower, ['mixed', 'null'], true) ? '?' : '';

        return $nullablePrefix.$name;
    }
}
