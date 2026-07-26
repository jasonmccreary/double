<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface BookRepositoryInterface
{
    public function find(int $id): ?Book;

    public function save(Book $book): bool;

    public function delete(int $id): void;

    public function count(): int;
}
