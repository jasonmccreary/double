<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * One method per row of SafeDefaultResolver's safe-default-by-return-type
 * table — a single purpose-built fixture for its tests instead of
 * scattering return types across unrelated interfaces.
 */
interface SafeDefaultInterface
{
    public function returnsVoid(): void;

    public function returnsBool(): bool;

    public function returnsInt(): int;

    public function returnsFloat(): float;

    public function returnsString(): string;

    public function returnsArray(): array;

    public function returnsIterable(): iterable;

    public function returnsNullable(): ?string;

    public function returnsMixed(): mixed;

    public function returnsNoType();

    public function returnsSelf(): self;

    public function returnsStatic(): static;

    public function returnsEnum(): Suit;

    public function returnsUnion(): int|string;

    public function returnsNullableUnion(): int|string|null;
}
