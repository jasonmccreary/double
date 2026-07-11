<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Loose mode: thrown when an unconfigured call chain goes more than one
 * fabricated stand-in deep (see SafeDefaultResolver and ARCHITECTURE.md,
 * "Modes: Loose, Strict, Passthru" — "Guardrails on fabrication"). Not a
 * TypeError, not silent, and not unbounded recursion — a clear, named stop
 * telling the person to configure that call themselves. Not final: see
 * ARCHITECTURE.md's "PHPUnit integration" —
 * PHPUnitFabricationLimitExceededException extends this.
 */
class FabricationLimitExceededException extends TestDoubleException
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $returnType,
        public readonly int $limit,
    ) {
        parent::__construct(self::renderMessage($label, $method));
    }

    /**
     * Static (not just private) so PHPUnitFabricationLimitExceededException
     * — which cannot extend this class, see ARCHITECTURE.md's "PHPUnit
     * integration" — renders byte-identical prose without duplicating the
     * sprintf. Takes only $label/$method: $returnType and $limit are kept as
     * stored fields for anything inspecting the exception programmatically
     * (see TestDoubleException's getDiagnostic()), but the message itself is
     * deliberately shorter than "everything the object knows," and leads
     * with a concrete, pasteable fix (built from the actual label/method
     * involved) rather than more prose — see ARCHITECTURE.md, "Guardrails
     * on fabrication."
     */
    public static function renderMessage(string $label, string $method): string
    {
        return sprintf(
            'Test double "%s" only fabricates one call chain deep — configure "%s()" '
            .'explicitly: $%s->allows(\'%s\')->returns(...);',
            $label,
            $method,
            self::suggestedVariableName($label),
            $method,
        );
    }
}
