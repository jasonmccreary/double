<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\PassthruAutoInstantiationDiagnostic;

/**
 * Thrown when ->passthru() is called with no argument and reflection-based
 * auto-instantiation of the target fails. See ARCHITECTURE.md, "Passthru."
 */
final class PassthruAutoInstantiationException extends TestDoubleException
{
    public static function isInterface(string $target): self
    {
        return new self(PassthruAutoInstantiationDiagnostic::isInterface($target));
    }

    public static function constructionFailed(string $target, \Throwable $exception): self
    {
        return new self(PassthruAutoInstantiationDiagnostic::constructionFailed($target, $exception));
    }
}
