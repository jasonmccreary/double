<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when expects()/allows() is configured for a method the double's
 * target never declared.
 */
final class UnknownMethodException extends TestDoubleException
{
    public static function forMethod(string $target, string $method): self
    {
        return new self(sprintf(
            'Cannot configure "%s" on a test double of "%s": no such method is declared there.',
            $method,
            $target,
        ));
    }
}
