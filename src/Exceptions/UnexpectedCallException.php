<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Strict mode: thrown immediately when a call matches no configured
 * expects()/allows() expectation. See ARCHITECTURE.md, "Modes: Loose,
 * Strict, Passthru" — "Any unmatched call throws immediately... no
 * fabrication, no defaults." Not final: see ARCHITECTURE.md's "PHPUnit
 * integration" — PHPUnitUnexpectedCallException extends this.
 */
class UnexpectedCallException extends TestDoubleException
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $method, $argumentsDescription, $fabricated));
    }

    /**
     * Static (not just private) so PHPUnitUnexpectedCallException — which
     * cannot extend this class, since it must extend PHPUnit's
     * AssertionFailedError instead, see ARCHITECTURE.md's "PHPUnit
     * integration" — renders byte-identical prose without duplicating the
     * sprintf.
     */
    public static function renderMessage(string $label, string $method, string $argumentsDescription, bool $fabricated): string
    {
        return sprintf(
            'Test double "%s" got an unexpected call to "%s(%s)" — Strict mode requires every '
            .'call configured. Add: $%s->allows(\'%s\')->returns(...);%s',
            $label,
            $method,
            $argumentsDescription,
            self::suggestedVariableName($label),
            $method,
            self::fabricatedNote($fabricated),
        );
    }
}
