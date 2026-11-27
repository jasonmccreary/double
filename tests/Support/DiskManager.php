<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * Mirrors Laravel's FilesystemManager: get() is protected (it resolves a
 * disk by name), and __call() forwards everything else to the default
 * disk. An outside get('tags.json') lands in __call() and reaches the
 * disk's get(), never this one.
 */
class DiskManager
{
    public function disk(?string $name = null): DiskInterface
    {
        return $this->get($name ?? 'local');
    }

    protected function get(string $name): DiskInterface
    {
        throw new \RuntimeException("Disk [{$name}] is not configured.");
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->{$method}(...$parameters);
    }
}
