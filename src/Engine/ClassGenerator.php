<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\DoubleInterface;
use JMac\Testing\Exceptions\InvalidDoubleTargetException;
use JMac\Testing\Exceptions\ReservedNameCollisionException;
use JMac\Testing\Exceptions\StaticMethodException;

/**
 * @internal
 *
 * Reflects a target class/interface and eval()s a new class that either
 * extends or implements it, overriding every overridable method to funnel
 * through ProxyBehavior::intercept().
 */
final class ClassGenerator
{
    private const RESERVED_METHODS = ['instance', 'expects', 'allows', 'strict', 'passthru', 'received', 'unused', 'verify'];

    /**
     * Magic methods with a fixed, reflectable signature — a real single entry
     * point, not PHP's dynamic-dispatch mechanism for members that don't
     * exist (__get/__set/__isset/__unset/__call/__callStatic, which stay
     * blocked). These get treated like any other method: overridden by
     * ClassGenerator and configurable via Double::expects()/allows().
     *
     * __set_state isn't listed despite having a fixed signature — it's
     * always static, and a static method is never overridden or
     * configurable, so a carve-out for it would only matter to
     * assertNoAbstractMagicMethods(), which rejects an abstract one rather
     * than letting it get a throwing stub.
     */
    public const DOUBLEABLE_MAGIC_METHODS = ['__invoke', '__toString', '__serialize', '__unserialize', '__clone'];

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

    public function generate(string $target, bool $override = false): string
    {
        if (! class_exists($target) && ! interface_exists($target)) {
            throw InvalidDoubleTargetException::doesNotExist($target);
        }

        $reflection = new \ReflectionClass($target);

        $keyword = $reflection->isInterface() ? 'implements' : 'extends';

        return $this->generateFromReflections([$reflection], [$target], $keyword, $override);
    }

    /**
     * @internal Used by SafeDefaultResolver (fabricating an
     * intersection-typed return) and Double::for() (a direct
     * multi-target double). A class target, when present, must come first;
     * all remaining targets must be interfaces.
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

        $keyword = $reflections[0]->isInterface() ? 'implements' : 'extends';

        return $this->generateFromReflections($reflections, $targets, $keyword, override: false);
    }

    /**
     * @param  list<string>  $targets
     */
    private function assertValidIntersectionTargets(array $targets): void
    {
        $seen = [];

        foreach ($targets as $index => $target) {
            if (isset($seen[$target])) {
                throw InvalidDoubleTargetException::duplicateTarget($target);
            }

            $seen[$target] = true;

            if (interface_exists($target) || ($index === 0 && class_exists($target))) {
                continue;
            }

            if (! class_exists($target)) {
                throw InvalidDoubleTargetException::doesNotExist($target);
            }

            throw class_exists($targets[0])
                ? InvalidDoubleTargetException::multipleClasses(implode('&', $targets))
                : InvalidDoubleTargetException::mustBeInterface($target);
        }
    }

    /**
     * @param  list<\ReflectionClass>  $reflections
     * @param  list<string>  $targets
     */
    private function generateFromReflections(array $reflections, array $targets, string $keyword, bool $override): string
    {
        // Reflects whatever PHP actually compiled, not the source on disk —
        // `final` is permanent for the rest of the process once a class is
        // loaded, unless Double::bypassFinals() rewrote it out of the source
        // before this was the thing that first triggered loading it (see
        // FinalBypass). Nothing short of that changes what this check sees,
        // which is why the exception below names that escape hatch directly
        // instead of leaving a dead end.
        if ($reflections[0]->isFinal()) {
            throw InvalidDoubleTargetException::isFinal($targets[0]);
        }

        $cacheKey = $this->cacheKey($targets, $override);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $collisions = $this->reservedNameCollisions($reflections);

        if ($collisions !== [] && ! $override) {
            throw ReservedNameCollisionException::forCollisions(implode('&', $targets), $collisions);
        }

        $this->assertNotEnumInterface($targets, $reflections);
        $this->assertNoClassAbstractStaticMethods($targets, $reflections);
        $this->assertNoAbstractMagicMethods($targets, $reflections);
        $this->assertNoStaticInstanceClash($targets, $reflections);
        $this->assertNoAbstractPropertyHooks($targets, $reflections);

        $className = $this->generateClassName($reflections);

        // Bare iff override actually resolved a real collision — passing
        // override: true against a target with nothing to collide with
        // generates the exact same class an ordinary double would.
        eval($this->buildSource($className, $keyword, $targets, $reflections, bare: $collisions !== []));

        return self::$cache[$cacheKey] = $className;
    }

    /**
     * Sorted, not positional — generateForIntersection() merges overridable
     * methods across targets regardless of argument order, so `[A, B]` and
     * `[B, A]` should share one cached class rather than generating twice.
     * $override is folded in separately, not sorted with the targets: the
     * same target set generates a structurally different class (bare or
     * not) depending on it, so the two must never collide in the cache.
     *
     * @param  list<string>  $targets
     */
    private function cacheKey(array $targets, bool $override): string
    {
        $sorted = $targets;
        sort($sorted);

        return implode('&', $sorted).($override ? '|override' : '');
    }

    /**
     * Public and protected only — a protected reserved-name method still
     * collides (the generated class must widen it to public to satisfy
     * DoubleInterface, which PHP rejects as an uncatchable fatal, not a
     * catchable error), but a private one doesn't: PHP never treats a
     * private method as inherited, so a subclass declaring the same name
     * at any visibility has nothing to conflict with.
     *
     * @param  list<\ReflectionClass>  $reflections
     * @return list<string>
     */
    private function reservedNameCollisions(array $reflections): array
    {
        $declared = [];

        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                $declared[] = strtolower($method->getName());
            }
        }

        return array_values(array_intersect(self::RESERVED_METHODS, $declared));
    }

    /**
     * PHP lets only an enum implement UnitEnum (and BackedEnum, which
     * extends it), so the generated class would be an uncatchable eval()-time
     * fatal error. A fabricated double for a `\BackedEnum` return type lands
     * here too, since SafeDefaultResolver sees an interface, not an enum.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNotEnumInterface(array $targets, array $reflections): void
    {
        foreach ($reflections as $reflection) {
            if ($reflection->getName() === \UnitEnum::class || $reflection->isSubclassOf(\UnitEnum::class)) {
                throw InvalidDoubleTargetException::isEnumInterface(implode('&', $targets));
            }
        }
    }

    /**
     * Only an interface's abstract static methods get a stub
     * (buildStaticStub()): an interface static factory is a contract the
     * double has to satisfy, while stubbing a class's own is static mocking,
     * which Double doesn't attempt. An abstract class that leaves an
     * interface's static unimplemented is rejected the same as one declaring
     * its own.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoClassAbstractStaticMethods(array $targets, array $reflections): void
    {
        foreach ($reflections as $reflection) {
            if ($reflection->isInterface()) {
                continue;
            }

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                if ($method->isStatic() && $method->isAbstract()) {
                    throw InvalidDoubleTargetException::hasAbstractStaticMethod(implode('&', $targets), $method->getName());
                }
            }
        }
    }

    /**
     * Intersection targets that declare the same method name as static on
     * one and instance on the other: no class can implement both, and the
     * generated one would redeclare the method — an uncatchable eval()-time
     * fatal error instead of this exception.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoStaticInstanceClash(array $targets, array $reflections): void
    {
        $instanceMethods = array_map(
            static fn (\ReflectionMethod $method): string => strtolower($method->getName()),
            $this->overridableMethods($reflections),
        );

        foreach ($this->abstractStaticMethods($reflections) as $method) {
            if (in_array(strtolower($method->getName()), $instanceMethods, true)) {
                throw InvalidDoubleTargetException::hasStaticInstanceClash(implode('&', $targets), $method->getName());
            }
        }
    }

    /**
     * An abstract magic method (anything starting with "__") would leave the
     * generated `final class` not implementing it — caught here, before
     * eval(), instead of surfacing as an uncatchable fatal error from inside
     * the eval()'d source. DOUBLEABLE_MAGIC_METHODS are exempt, since
     * overridableMethods() does override them. Static ones (__callStatic, in
     * practice) are rejected too rather than stubbed: the stub would report
     * `__callStatic` instead of the method the caller actually called.
     *
     * @param  list<string>  $targets
     * @param  list<\ReflectionClass>  $reflections
     */
    private function assertNoAbstractMagicMethods(array $targets, array $reflections): void
    {
        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                if (str_starts_with($method->getName(), '__')
                    && ! in_array($method->getName(), self::DOUBLEABLE_MAGIC_METHODS, true)
                    && $method->isAbstract()
                ) {
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
        // library's floor is 8.2) — load-bearing, not defensive dead code: on
        // 8.2/8.3 a property can't be abstract at all yet.
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
    private function buildSource(string $fqcn, string $keyword, array $targets, array $reflections, bool $bare): string
    {
        $position = strrpos($fqcn, '\\');
        $namespace = substr($fqcn, 0, $position);
        $shortName = substr($fqcn, $position + 1);

        $parents = array_map(
            static fn (string $target): string => '\\'.ltrim($target, '\\'),
            $targets,
        );

        // An ordinary generated double implements DoubleInterface for real,
        // not just as a docblock fiction for Double::for()'s
        // @template/@return pairing — every one of the eight control verbs
        // is a real, callable method. A bare (override-collision) double
        // can't do that: at least one of those verb names is a real method
        // the target itself declares, so DoubleControlMethods is never
        // mixed in at all, and the double only promises IdentifiableDouble
        // — real identity tracking, nothing else. Double::for() wraps it in
        // OverriddenDouble, which is what actually carries the eight verbs
        // for this double, forwarding each one back to the double this
        // builds. A class target is extended; any interface targets join
        // the control interface in the implements clause.
        $controlInterface = '\\'.ltrim($bare ? IdentifiableDouble::class : DoubleInterface::class, '\\');
        $parent = $keyword === 'extends' ? array_shift($parents) : null;
        $parents[] = $controlInterface;
        $inheritance = ($parent === null ? '' : sprintf('extends %s ', $parent))
            .'implements '.implode(', ', $parents);

        $overridable = $this->overridableMethods($reflections);

        // A protected class method that an interface target also declares
        // must be public, or PHP refuses the generated class outright.
        $interfaceMethods = [];

        foreach ($reflections as $reflection) {
            if ($reflection->isInterface()) {
                foreach ($reflection->getMethods() as $method) {
                    $interfaceMethods[strtolower($method->getName())] = true;
                }
            }
        }

        $methods = implode("\n", array_map(
            fn (\ReflectionMethod $method): string => $this->buildMethod($method, isset($interfaceMethods[strtolower($method->getName())])),
            $overridable,
        ));

        $stubs = array_map(
            fn (\ReflectionMethod $method): string => $this->buildStaticStub($method, implode('&', $targets)),
            $this->abstractStaticMethods($reflections),
        );

        if ($stubs !== []) {
            $methods .= "\n".implode("\n", $stubs);
        }

        // Inline passthru (see DoubleControlMethods::passthru()) needs a way to
        // run a method's real, inherited body directly on the double itself —
        // `parent::{$method}(...)` only compiles inside a class that actually
        // has a parent, so this is skipped entirely for an `implements` target
        // (an interface has no body to run). Every overridable method gets one
        // of these regardless of whether inline passthru is ever used on a
        // given double — cheap to generate, and simpler than threading "will
        // this double ever need it" through class generation.
        if ($keyword === 'extends') {
            $methods .= "\n".implode("\n", array_map($this->buildRealMethod(...), $overridable));
        }

        // A class extending a readonly one must be readonly itself (every
        // property on a readonly class stays readonly all the way down the
        // hierarchy, so PHP refuses a non-readonly subclass outright). Only
        // relevant for `extends` — interfaces can't be readonly, so an
        // interface-only target list never triggers this. Harmless either
        // way: the identity token added by DoubleIdentity is already readonly.
        $readOnly = $keyword === 'extends' && $reflections[0]->isReadOnly() ? 'readonly ' : '';

        return sprintf(
            "namespace %s;\n\n%sfinal class %s %s\n{\n    use \\%s;\n\n%s\n}\n",
            $namespace,
            $readOnly,
            $shortName,
            $inheritance,
            $bare ? DoubleIdentity::class : DoubleControlMethods::class,
            $methods,
        );
    }

    /**
     * Merges overridable methods across every target, keyed by lowercased
     * name, as PHP treats method names case-insensitively — the
     * class takes precedence, including methods left unoverridden. Among
     * interfaces, the first declaration wins.
     *
     * @param  list<\ReflectionClass>  $reflections
     * @return list<\ReflectionMethod>
     */
    private function overridableMethods(array $reflections): array
    {
        $methods = [];
        $classMethods = [];

        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                $name = strtolower($method->getName());

                if (isset($methods[$name]) || isset($classMethods[$name])) {
                    continue;
                }

                if (! $reflection->isInterface()) {
                    $classMethods[$name] = true;
                }

                // Static excluded: ProxyBehavior::intercept() takes $this and
                // dispatches through it, and a static call has no $this to give it.
                // A concrete static method is just inherited unoverridden; an
                // interface's abstract one gets a throwing stub from
                // buildStaticStub(), and a class's own is rejected up front.
                if ($method->isFinal()
                    || $method->isStatic()
                    || $method->isConstructor()
                    || $method->isDestructor()
                    || (str_starts_with($method->getName(), '__') && ! in_array($method->getName(), self::DOUBLEABLE_MAGIC_METHODS, true))
                ) {
                    continue;
                }

                $methods[$name] = $method;
            }
        }

        return array_values($methods);
    }

    /**
     * Abstract static methods across every target, keyed by name like
     * overridableMethods() — the generated `final class` must implement each
     * one to compile, even though none can be configured. Every one comes
     * from an interface target: assertNoClassAbstractStaticMethods() rejects
     * the rest first. Methods already provided by the class are inherited.
     *
     * @param  list<\ReflectionClass>  $reflections
     * @return list<\ReflectionMethod>
     */
    private function abstractStaticMethods(array $reflections): array
    {
        $methods = [];
        $classMethods = [];

        foreach ($reflections as $reflection) {
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED) as $method) {
                $name = strtolower($method->getName());

                if (! $reflection->isInterface()) {
                    $classMethods[$name] = true;
                } elseif ($method->isStatic() && $method->isAbstract() && ! isset($methods[$name]) && ! isset($classMethods[$name])) {
                    $methods[$name] = $method;
                }
            }
        }

        return array_values($methods);
    }

    /**
     * A plain `throw` statement rather than buildMethodFromCall()'s
     * `return <expr>;` — a `never` return type rejects any `return` at
     * compile time.
     */
    private function buildStaticStub(\ReflectionMethod $method, string $target): string
    {
        $body = sprintf(
            'throw new \\%s(%s, %s, called: true);',
            StaticMethodException::class,
            var_export($target, true),
            var_export($method->getName(), true),
        );

        return $this->buildMethodFromBody($method, $method->getName(), $body, 'public static');
    }

    private function buildMethod(\ReflectionMethod $method, bool $public = false): string
    {
        $call = sprintf(
            '\\%s::intercept($this, %s, %s)',
            ProxyBehavior::class,
            var_export($method->getName(), true),
            $this->buildArguments($method),
        );

        // Mirrors the real method's own visibility: this is a genuine
        // override of that method, and PHP requires an override's visibility
        // to be at least as permissive as what it overrides.
        $visibility = $method->isProtected() && ! $public ? 'protected' : 'public';

        return $this->buildMethodFromCall($method, $method->getName(), $call, $visibility);
    }

    /**
     * func_get_args() copies values, so reference parameters must be forwarded
     * as variables for writes to reach the caller. Slicing to func_num_args()
     * keeps omitted optionals out, including object defaults represented by null
     * in the generated signature.
     */
    private function buildArguments(\ReflectionMethod $method): string
    {
        $arguments = [];
        $variadic = null;
        $hasReferences = false;

        foreach ($method->getParameters() as $parameter) {
            $hasReferences = $hasReferences || $parameter->isPassedByReference();

            if ($parameter->isVariadic()) {
                $variadic = '$'.$parameter->getName();
            } else {
                $arguments[] = ($parameter->isPassedByReference() ? '&' : '').'$'.$parameter->getName();
            }
        }

        if (! $hasReferences) {
            return 'func_get_args()';
        }

        $arguments[] = '...'.($variadic ?? sprintf('array_slice(func_get_args(), %d)', count($arguments)));

        return sprintf('array_slice([%s], 0, func_num_args())', implode(', ', $arguments));
    }

    /**
     * Passthru's real-body counterpart to buildMethod() above: same
     * signature, but the body runs the method's actual inherited
     * implementation via `parent::` instead of funneling through
     * ProxyBehavior::intercept(). Named with a "__td_real_" prefix, never
     * exposed as part of the double's own configurable API — see
     * ProxyBehavior::handleUnmatchedCall()'s Passthru branch, the only
     * caller.
     */
    private function buildRealMethod(\ReflectionMethod $method): string
    {
        $name = $method->getName();
        $call = sprintf('parent::%s(...%s)', $name, $this->buildArguments($method));

        // Always public, regardless of the real method's own visibility —
        // unlike buildMethod() above, this isn't overriding anything (its
        // name is invented, not inherited), so there's no visibility to
        // mirror. It has to be callable from ProxyBehavior, a class outside
        // the double entirely: calling a protected/private method from
        // outside its own class scope doesn't raise a visibility error —
        // PHP falls back to the target's __call() instead, if it has one
        // (Laravel's Macroable trait does), which then rejects the call as
        // an unknown method. Public is what avoids that silently wrong path.
        return $this->buildMethodFromCall($method, '__td_real_'.$name, $call, 'public');
    }

    /**
     * Shared body-building for buildMethod() and buildRealMethod() — they
     * differ only in $overrideName, $visibility, and what expression $call
     * evaluates.
     */
    private function buildMethodFromCall(\ReflectionMethod $method, string $overrideName, string $call, string $visibility): string
    {
        // Once declared by-reference, `return $call;` directly fails — "Only
        // variable references should be returned by reference," since the
        // call's result isn't itself a reference — so assigning to a local
        // variable first is what makes the by-ref return actually silent.
        $body = match (true) {
            $method->returnsReference() => sprintf("\$__td_result = %s;\n\n        return \$__td_result;", $call),
            // `never` rejects a `return` at compile time just like `void`.
            in_array($this->returnTypeString($method), ['void', 'never'], true) => sprintf('%s;', $call),
            default => sprintf('return %s;', $call),
        };

        return $this->buildMethodFromBody($method, $overrideName, $body, $visibility);
    }

    /**
     * Shared signature-building: every generated method needs the exact
     * same parameters and return type as the method it stands in for.
     */
    private function buildMethodFromBody(\ReflectionMethod $method, string $overrideName, string $body, string $modifiers): string
    {
        $declaringClass = $method->getDeclaringClass();

        $parameters = implode(', ', array_map(
            fn (\ReflectionParameter $parameter): string => $this->buildParameter($parameter, $declaringClass),
            $method->getParameters(),
        ));

        $returnTypeString = $this->returnTypeString($method);
        $returnDeclaration = $returnTypeString !== null ? ': '.$returnTypeString : '';

        // A by-reference-returning method (`function &foo()`) requires the
        // override to declare the same leading "&", or PHP rejects it as
        // incompatible at eval() time.
        $reference = $method->returnsReference() ? '&' : '';

        return sprintf(
            "    %s function %s%s(%s)%s\n    {\n        %s\n    }\n",
            $modifiers,
            $reference,
            $overrideName,
            $parameters,
            $returnDeclaration,
            $body,
        );
    }

    private function returnTypeString(\ReflectionMethod $method): ?string
    {
        // getReturnType() is null for a method whose only declared return type is
        // "tentative" — the mechanism built-in interfaces like ArrayAccess and
        // Countable use while a return type is still being phased in as enforced.
        // Falling back to getTentativeReturnType() stops the override from being
        // built with no return type at all, which PHP flags as a deprecated
        // incompatibility against the interface's tentative one.
        $returnType = $method->getReturnType() ?? ($method->hasTentativeReturnType() ? $method->getTentativeReturnType() : null);

        return $returnType !== null ? $this->stringifyType($returnType, $method->getDeclaringClass()) : null;
    }

    private function buildParameter(\ReflectionParameter $parameter, \ReflectionClass $declaringClass): string
    {
        $type = $parameter->getType();

        $byRef = $parameter->isPassedByReference() ? '&' : '';
        $variadic = $parameter->isVariadic() ? '...' : '';

        $default = '';
        $forceNullable = false;

        if (! $parameter->isVariadic() && $parameter->isDefaultValueAvailable()) {
            if ($parameter->isDefaultValueConstant()) {
                $default = ' = '.$this->qualifyConstantName($parameter->getDefaultValueConstantName(), $declaringClass);
            } elseif ($this->isReproducibleDefault($value = $parameter->getDefaultValue())) {
                $default = ' = '.var_export($value, true);
            } else {
                // Not a scalar/array/enum-case/resolvable-constant — in valid PHP this can
                // only be a PHP 8.1+ "new in initializers" object default (e.g. `= new
                // MissingValue`), which getDefaultValue() already evaluated into a real
                // instance. var_export() on an arbitrary object without __set_state()
                // emits a static method call, which isn't a legal constant expression in
                // a parameter default — a compile-time fatal once eval()'d. Generated
                // methods forward only the supplied arguments (see buildArguments()), so
                // the real method supplies an omitted parameter's default. Substitute a
                // benign `null` and widen the type to accept it if it doesn't already.
                $default = ' = null';
                $forceNullable = $type !== null && ! $type->allowsNull();
            }
        }

        $typeDeclaration = $type !== null ? $this->stringifyType($type, $declaringClass, $forceNullable).' ' : '';

        return sprintf('%s%s%s$%s%s', $typeDeclaration, $byRef, $variadic, $parameter->getName(), $default);
    }

    /**
     * Whether $value round-trips through var_export() as a legal PHP constant
     * expression: scalars, null, enum cases (var_export() has emitted these as
     * `\Enum::CASE` since PHP 8.1), and arrays composed only of those,
     * recursively. A plain object doesn't have this property — var_export()
     * falls back to `Class::__set_state(...)`, a static method call, which
     * PHP does not allow in a default-value position.
     */
    private function isReproducibleDefault(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->isReproducibleDefault($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value) || $value instanceof \UnitEnum;
    }

    /**
     * The generated class lives in its own namespace, so constant names must
     * be forced absolute — otherwise PHP resolves them relative to the generated
     * namespace instead of the one the original signature was written in.
     * Reflection reports an unqualified constant under the original namespace,
     * so it falls back to the global constant when that namespaced one isn't
     * defined, as PHP does. A trait's __CLASS__ names the class using the trait.
     *
     * "self"/"parent" prefixes are resolved against the declaring class first,
     * same reasoning as stringifyNamedType(): left as literal keywords, they'd
     * re-resolve inside the generated class's own body instead of the class
     * that actually declared the default. Unlike a type, there's no
     * contravariance escape hatch here — "\self::CONST" is simply an invalid
     * class name, an eval()-time fatal rather than a compatibility rejection.
     * "static" is left as a bare keyword (never backslash-qualified) for the
     * same late-static-binding reason stringifyNamedType() leaves it alone.
     */
    private function qualifyConstantName(string $name, \ReflectionClass $declaringClass): string
    {
        if ($name === '__CLASS__') {
            return '\\'.$declaringClass->getName().'::class';
        }

        if (! str_contains($name, '::')) {
            if (! defined($name) && defined($global = substr(strrchr('\\'.$name, '\\'), 1))) {
                $name = $global;
            }

            return '\\'.$name;
        }

        [$prefix, $const] = explode('::', $name, 2);

        $resolved = match (strtolower($prefix)) {
            'self' => $declaringClass->getName(),
            'parent' => $declaringClass->getParentClass()->getName(),
            'static' => $prefix,
            default => $prefix,
        };

        return $resolved === 'static' || str_starts_with($resolved, '\\')
            ? "{$resolved}::{$const}"
            : "\\{$resolved}::{$const}";
    }

    /**
     * $forceNullable is only ever set by buildParameter() substituting a
     * benign `null` default for an unreproducible one (see
     * isReproducibleDefault()) on a type that didn't already allow null —
     * widening the type to match is what keeps `= null` legal there.
     */
    private function stringifyType(\ReflectionType $type, \ReflectionClass $declaringClass, bool $forceNullable = false): string
    {
        if ($type instanceof \ReflectionNamedType) {
            return $this->stringifyNamedType($type, $declaringClass, $forceNullable);
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $intersection = $this->stringifyIntersectionType($type, $declaringClass);

            // Plain intersection types have no nullable-shorthand syntax of their
            // own — DNF (PHP 8.2+) is what makes "(A&B)|null" legal.
            return $forceNullable ? "({$intersection})|null" : $intersection;
        }

        // ReflectionUnionType: members are either ReflectionNamedType or,
        // for a member like (A&B)|C, a nested ReflectionIntersectionType.
        $members = implode('|', array_map(
            fn (\ReflectionType $member): string => $member instanceof \ReflectionIntersectionType
                ? '('.$this->stringifyIntersectionType($member, $declaringClass).')'
                : $this->stringifyNamedType($member, $declaringClass),
            $type->getTypes(),
        ));

        return $forceNullable && ! $type->allowsNull() ? "{$members}|null" : $members;
    }

    private function stringifyIntersectionType(\ReflectionIntersectionType $type, \ReflectionClass $declaringClass): string
    {
        return implode('&', array_map(
            fn (\ReflectionNamedType $member): string => $this->stringifyNamedType($member, $declaringClass),
            $type->getTypes(),
        ));
    }

    /**
     * Mirrors PHP's own ReflectionNamedType::__toString() (leading "?" iff
     * allowsNull() and the type isn't "mixed"/"null" itself) but additionally
     * forces class/interface names absolute with a leading "\", which
     * __toString() does not do — necessary because the generated class lives
     * in its own namespace, not the target's.
     *
     * "self" and "parent" are resolved to the real class they refer to in the
     * method's declaring class, rather than left as literal keywords. Left
     * literal, they'd re-resolve inside the generated class's own body — e.g.
     * "self" on a method declared in a grandparent would become the generated
     * class itself, a narrower type than the original. That's fine for a
     * covariant return type, but PHP rejects it on a parameter, which must
     * stay the same or wider. "static" is left alone: it's return-type-only,
     * so this contravariance problem can't apply, and rewriting it would
     * break the late static binding it exists for.
     */
    private function stringifyNamedType(\ReflectionNamedType $type, \ReflectionClass $declaringClass, bool $forceNullable = false): string
    {
        $name = $type->getName();
        $lower = strtolower($name);

        $name = match ($lower) {
            'self' => $declaringClass->getName(),
            'parent' => $declaringClass->getParentClass()->getName(),
            default => $name,
        };

        if (! $type->isBuiltin() && $lower !== 'static') {
            $name = '\\'.ltrim($name, '\\');
        }

        $nullablePrefix = ($type->allowsNull() || $forceNullable) && ! in_array($lower, ['mixed', 'null'], true) ? '?' : '';

        return $nullablePrefix.$name;
    }
}
