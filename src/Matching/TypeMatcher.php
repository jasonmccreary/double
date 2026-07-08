<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Argument::type($class) — matches any instance of the given class or
 * interface. See ARCHITECTURE.md's example:
 * `Argument::type(Book::class)`.
 */
final class TypeMatcher implements Matcher
{
    public function __construct(
        /** @var class-string */
        private readonly string $type,
    ) {}

    public function matches(mixed $actual): bool
    {
        return $actual instanceof $this->type;
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

        return sprintf('expected an instance of %s, got %s', $this->type, get_debug_type($actual));
    }
}
