<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * A double's mode is set once and is immutable after that (see
 * ARCHITECTURE.md, "Modes: Loose, Strict, Passthru"). Thrown when setup
 * code tries to set it more than once, e.g. ->strict()->strict() or
 * ->strict()->passthru($x).
 */
class ModeConfigurationException extends TestDoubleException
{
    public function __construct(
        public readonly string $label,
        public readonly string $current,
        public readonly string $attempted,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct($this->render());
    }

    private function render(): string
    {
        return sprintf(
            'Test double `%s` is already "%s". You can\'t set its mode again.%s',
            $this->label,
            strtolower($this->current),
            self::fabricatedNote($this->fabricated),
        );
    }
}
