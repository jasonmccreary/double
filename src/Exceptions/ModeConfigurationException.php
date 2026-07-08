<?php

declare(strict_types=1);

namespace TestDouble\Exceptions;

/**
 * A double's mode is set once and is immutable after that (see
 * ARCHITECTURE.md, "Modes: Loose, Strict, Passthru"). Thrown when setup
 * code tries to set it more than once, e.g. ->strict()->strict() or
 * ->strict()->passthru($x).
 */
final class ModeConfigurationException extends TestDoubleException
{
    public static function alreadyConfigured(string $label, string $current, string $attempted): self
    {
        return new self(sprintf(
            'Test double "%s" already has its mode set to %s; cannot also set it to %s. '
            . 'A double\'s mode is set once, at setup time, and is immutable after that.',
            $label,
            $current,
            $attempted,
        ));
    }
}
