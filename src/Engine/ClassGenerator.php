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
 * through ProxyBehavior::intercept().
 */
final class ClassGenerator
{
    private const RESERVED_METHODS = ['expects', 'allows', 'strict', 'passthru', 'received', 'verify'];

    private static int $counter = 0;

    /**
     * A second call to generate()/generateForIntersection() for the same
     * target(s) returns the already-declared class instead of eval()ing a
     * fresh one. Uncached, this cost ~1-1.5KB of process memory per public
     * method on the target, per call, permanently.
     *
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
     * @internal Used by SafeDefaultResolver (fabricating an
     * intersection-typed return) and TestDouble::for() (a direct
     * multi-target double). Intersection members are always interfaces in
     * PHP, so this validates every target actually is one instead of
     * branching between extends/implements.
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
     * Sorted, not positional — generateForIntersection() merges overridable
     * methods across targets regardless of argument order, so `[A, B]` and
     * `[B, A]` should share one cached class rather than generating twice.
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
     * A concrete static method is inherited unoverridden (harmless — real
     * code, just not interceptable). An abstract one would leave the
     * generated `final class` not actually implementing it — caught here,
     * before eval(), instead of surfacing as an uncatchable fatal error
     * from inside the eval()'d source.
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
     * Same failure shape as assertNoAbstractStaticMethods() above, for magic
     * methods (anything starting with "__") instead of static ones. Runs
     * after the static check, so something both static and magic
     * (__callStatic, in practice) is already caught there first.
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
     * Same failure shape again, for PHP 8.4+ hooked properties
     * (`public string $name { get; }`) — this generator never reasons about
     * properties otherwise, only methods.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoAbstractPropertyHooks(array $targets, array $reflections): void
    {
        // ReflectionProperty::isAbstract() doesn't exist before PHP 8.4 (this
        // library's floor is 8.3) — load-bearing, not defensive dead code: on
        // 8.3 a property can't be abstract at all yet.
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

        // Every generated double implements TestDoubleInterface for real, not just as
        // a docblock fiction for TestDouble::for()'s @template/@return pairing. A
        // single-class target uses `extends`, so the interface needs its own
        // `implements` clause; an interface target already uses `implements`, so it
        // just joins the list.
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
     * first reflection to declare a given method wins.
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

                // Static excluded: ProxyBehavior::intercept() takes $this and
                // dispatches through it, and a static call has no $this to give it.
                // A concrete static method is just inherited unoverridden; an
                // abstract one is already caught by assertNoAbstractStaticMethods().
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
        // "tentative" — the mechanism built-in interfaces like ArrayAccess and
        // Countable use while a return type is still being phased in as enforced.
        // Falling back to getTentativeReturnType() stops the override from being
        // built with no return type at all, which PHP flags as a deprecated
        // incompatibility against the interface's tentative one.
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
        // declare the same leading "&", or PHP rejects it as incompatible at eval()
        // time. Once declared by-reference, `return $call;` directly also fails —
        // "Only variable references should be returned by reference," since
        // intercept()'s result isn't itself a reference — so assigning to a local
        // variable first is what makes the by-ref return actually silent.
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
