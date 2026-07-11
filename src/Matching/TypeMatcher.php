<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Argument::type($type) — matches any instance of the given class or
 * interface, or any value of the given PHP builtin type (int/float/string/
 * bool/array/object/callable/iterable/null/mixed). Mirrors Mockery::type(),
 * confirmed against Mockery's own Matcher\Type source: it accepts the same
 * two kinds of $type interchangeably, not just class/interface names. See
 * ARCHITECTURE.md's example: `Argument::type(Book::class)` —
 * `Argument::type('int')` is the builtin-type form of the same verb.
 *
 * Builtin names never collide with a real class/interface name: every name
 * in BUILTIN_CHECKS is a reserved word in PHP, so none of them can ever
 * also be a class name — checking the builtin table first is unambiguous,
 * not a guess about which one the caller meant.
 */
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
