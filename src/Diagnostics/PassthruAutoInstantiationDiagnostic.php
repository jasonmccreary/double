<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * ->passthru() was called with no argument and the reflection-based
 * auto-instantiation attempt failed — either because the target is an
 * interface (no constructor to invoke) or because constructing it threw.
 * See ARCHITECTURE.md, "Passthru."
 */
final class PassthruAutoInstantiationDiagnostic implements Diagnostic
{
    public function __construct(
        public readonly string $target,
        public readonly string $reason,
    ) {}

    public static function isInterface(string $target): self
    {
        return new self($target, 'it is an interface, which has no constructor to invoke');
    }

    public static function constructionFailed(string $target, \Throwable $exception): self
    {
        return new self($target, sprintf('constructing it threw: %s', $exception->getMessage()));
    }
}
