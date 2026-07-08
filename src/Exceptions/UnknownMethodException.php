<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\UnknownMethodDiagnostic;

/**
 * Thrown when expects()/allows() is configured for a method the double's
 * target never declared.
 */
final class UnknownMethodException extends TestDoubleException
{
    public static function forMethod(string $target, string $method, bool $fabricated = false): self
    {
        return new self(new UnknownMethodDiagnostic($target, $method, $fabricated));
    }
}
