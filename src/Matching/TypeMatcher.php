<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

final class TypeMatcher implements Matcher
{
    private const BUILTIN_CHECKS = [
        'int' => 'is_int',
        'float' => 'is_float',
        'string' => 'is_string',
        'bool' => 'is_bool',
        'array' => 'is_array',
        'object' => 'is_object',
        'callable' => 'is_callable',
        'iterable' => 'is_iterable',
        'null' => 'is_null',
    ];

    public function __construct(
        private readonly string $type,
    ) {}

    public function matches(mixed $actual): bool
    {
        $lower = strtolower($this->type);

        if ($lower === 'mixed') {
            return true;
        }

        // Builtin names never collide with a real class/interface name — every
        // name in BUILTIN_CHECKS is a reserved word in PHP — so checking the
        // builtin table first is unambiguous, not a guess about caller intent.
        $check = self::BUILTIN_CHECKS[$lower] ?? null;

        return $check !== null ? $check($actual) : $actual instanceof $this->type;
    }

    public function describe(): string
    {
        return sprintf('type(%s)', $this->type);
    }

    public function explainMismatch(mixed $actual): ?string
    {
        if ($this->matches($actual)) {
            return null;
        }

        return sprintf('expected type %s, got %s', $this->type, get_debug_type($actual));
    }
}
