<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Exceptions\InvalidDoubleTargetException;
use JMac\Testing\Exceptions\ReservedNameCollisionException;

/**
 * @internal
 *
 * Reflects a target class/interface and eval()s a new class that either
 * extends or implements it, overriding every overridable method to funnel
 * through ProxyBehavior::intercept(). Uses the same eval() technique as
 * Mockery/Prophecy (see ARCHITECTURE.md's "Known scaffold-era
 * limitations") and does not yet cache generated classes per target —
 * each call to generate() produces a freshly named class, which sidesteps
 * "cannot redeclare class" without needing a cache; caching purely as a
 * performance improvement is explicitly deferred, not required for M1.
 *
 * Signature reconstruction always derives parameter/return type text from
 * Reflection's own type objects (never from re-parsing source), which is
 * what guarantees ClassGenerator never emits an implicit-nullable
 * parameter: PHP's ReflectionNamedType::__toString() always includes the
 * leading "?" whenever allowsNull() is true, regardless of whether the
 * original source spelled that nullability as `?Type` or as the
 * deprecated implicit `Type $x = null` form.
 *
 * Known gaps, deliberately not in M1 (see ARCHITECTURE.md, "Known
 * scaffold-era limitations to design around, not just inherit"):
 * magic methods are never overridden (left to inherited/default
 * behavior), and interfaces with hooked properties are not specially
 * handled.
 */
final class ClassGenerator
{
    private const RESERVED_METHODS = ['expects', 'allows', 'strict', 'passthru', 'received'];

    private static int $counter = 0;

    public function generate(string $target): string
    {
        if (!class_exists($target) && !interface_exists($target)) {
            throw InvalidDoubleTargetException::doesNotExist($target);
        }

        $reflection = new \ReflectionClass($target);

        if ($reflection->isFinal()) {
            throw InvalidDoubleTargetException::isFinal($target);
        }

        $this->assertNoReservedNameCollisions($target, $reflection);

        $className = $this->generateClassName($reflection);

        eval($this->buildSource($className, $target, $reflection));

        return $className;
    }

    private function assertNoReservedNameCollisions(string $target, \ReflectionClass $reflection): void
    {
        $declared = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        $collisions = array_values(array_intersect(self::RESERVED_METHODS, $declared));

        if ($collisions !== []) {
            throw ReservedNameCollisionException::forCollisions($target, $collisions);
        }
    }

    private function generateClassName(\ReflectionClass $reflection): string
    {
        $short = preg_replace('/[^A-Za-z0-9_]/', '_', $reflection->getShortName());

        return sprintf('JMac\Testing\\Engine\\Generated\\%s_%d', $short, ++self::$counter);
    }

    private function buildSource(string $fqcn, string $target, \ReflectionClass $reflection): string
    {
        $position = strrpos($fqcn, '\\');
        $namespace = substr($fqcn, 0, $position);
        $shortName = substr($fqcn, $position + 1);

        $keyword = $reflection->isInterface() ? 'implements' : 'extends';
        $methods = implode("\n", array_map(
            $this->buildMethod(...),
            $this->overridableMethods($reflection),
        ));

        return sprintf(
            "namespace %s;\n\nfinal class %s %s \\%s\n{\n    use \\%s;\n\n%s\n}\n",
            $namespace,
            $shortName,
            $keyword,
            ltrim($target, '\\'),
            DoubleControlMethods::class,
            $methods,
        );
    }

    /**
     * @return list<\ReflectionMethod>
     */
    private function overridableMethods(\ReflectionClass $reflection): array
    {
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED);

        return array_values(array_filter(
            $methods,
            static fn (\ReflectionMethod $method): bool =>
                !$method->isFinal()
                && !$method->isStatic()
                && !$method->isConstructor()
                && !$method->isDestructor()
                && !str_starts_with($method->getName(), '__'),
        ));
    }

    private function buildMethod(\ReflectionMethod $method): string
    {
        $name = $method->getName();
        $visibility = $method->isProtected() ? 'protected' : 'public';

        $parameters = implode(', ', array_map(
            $this->buildParameter(...),
            $method->getParameters(),
        ));

        $returnType = $method->getReturnType();
        $returnDeclaration = $returnType !== null ? ': ' . $this->stringifyType($returnType) : '';
        $isVoid = $returnType !== null && $this->stringifyType($returnType) === 'void';

        $call = sprintf(
            '\\%s::intercept(\\%s::stateFor($this), %s, func_get_args())',
            ProxyBehavior::class,
            TestDouble::class,
            var_export($name, true),
        );

        $body = $isVoid ? sprintf('%s;', $call) : sprintf('return %s;', $call);

        return sprintf(
            "    %s function %s(%s)%s\n    {\n        %s\n    }\n",
            $visibility,
            $name,
            $parameters,
            $returnDeclaration,
            $body,
        );
    }

    private function buildParameter(\ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $typeDeclaration = $type !== null ? $this->stringifyType($type) . ' ' : '';

        $byRef = $parameter->isPassedByReference() ? '&' : '';
        $variadic = $parameter->isVariadic() ? '...' : '';

        $default = '';

        if (!$parameter->isVariadic() && $parameter->isDefaultValueAvailable()) {
            $default = ' = ' . ($parameter->isDefaultValueConstant()
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
        return str_contains($name, '::') && !str_starts_with($name, '\\') ? '\\' . $name : $name;
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
                ? '(' . $this->stringifyIntersectionType($member) . ')'
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

        if (!$type->isBuiltin() && !in_array($lower, ['self', 'static', 'parent'], true)) {
            $name = '\\' . $name;
        }

        $nullablePrefix = $type->allowsNull() && !in_array($lower, ['mixed', 'null'], true) ? '?' : '';

        return $nullablePrefix . $name;
    }
}
