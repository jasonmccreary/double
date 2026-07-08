<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

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
        return new self(sprintf(
            'Unexpected call to "%s(%s)" on test double "%s": no configured expects()/allows() '
            . 'matches this call, and the double is in Strict mode.',
            $method,
            $argumentsDescription,
            $label,
        ));
    }
}
