<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\InvalidDoubleTargetDiagnostic;

/**
 * Thrown at TestDouble::for() time when the requested target cannot be
 * doubled at all: it doesn't exist, or it's a final class (which can't be
 * extended). See ARCHITECTURE.md, "Known scaffold-era limitations."
 */
final class InvalidDoubleTargetException extends TestDoubleException
{
    public static function doesNotExist(string $target): self
    {
        return new self(InvalidDoubleTargetDiagnostic::doesNotExist($target));
    }

    public static function isFinal(string $target): self
    {
        return new self(InvalidDoubleTargetDiagnostic::isFinal($target));
    }
}
