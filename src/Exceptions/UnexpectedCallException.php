<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\UnexpectedCallDiagnostic;

/**
 * Strict mode: thrown immediately when a call matches no configured
 * expects()/allows() expectation. See ARCHITECTURE.md, "Modes: Loose,
 * Strict, Passthru" — "Any unmatched call throws immediately... no
 * fabrication, no defaults."
 */
final class UnexpectedCallException extends TestDoubleException
{
    public static function forCall(string $label, string $method, string $argumentsDescription): self
    {
        return new self(new UnexpectedCallDiagnostic($label, $method, $argumentsDescription));
    }
}
