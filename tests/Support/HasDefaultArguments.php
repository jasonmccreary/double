<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

class HasDefaultArguments
{
    public const DEFAULT_MODE = 'constant';

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): array
    {
        return [func_num_args(), $column, $operator, $value, $boolean];
    }

    public function options(string $name, int $limit = 10, array $flags = ['a'], Suit $suit = Suit::Hearts, string $mode = self::DEFAULT_MODE): int
    {
        return func_num_args();
    }

    public function objectDefault(string $name, mixed $object = new NewInInitializerDefault, string $after = 'after'): int
    {
        return func_num_args();
    }

    public function variadic(string $name, string $next = 'next', string ...$rest): int
    {
        return func_num_args();
    }

    public function namedVariadic(string $name, string $next = 'next', string $third = 'third', string ...$rest): int
    {
        return func_num_args();
    }

    public function addTo(int &$total, int $amount = 1): void
    {
        $total += $amount;
    }

    public function other(string $name = 'name'): string
    {
        return $name;
    }
}
