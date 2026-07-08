<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown at TestDouble::for() time when the requested target cannot be
 * doubled at all: it doesn't exist, or it's a final class (which can't be
 * extended). See ARCHITECTURE.md, "Known scaffold-era limitations."
 */
final class InvalidDoubleTargetException extends TestDoubleException
{
    public static function doesNotExist(string $target): self
    {
        return new self(sprintf(
            'Cannot create a test double for "%s": no such class or interface exists.',
            $target,
        ));
    }

    public static function isFinal(string $target): self
    {
        return new self(sprintf(
            'Cannot create a test double for "%s": it is declared final, so it cannot be '
            .'doubled by extending it.',
            $target,
        ));
    }
}
