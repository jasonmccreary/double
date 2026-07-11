<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Loose mode: thrown when an unconfigured call chain goes more than one
 * fabricated stand-in deep (see SafeDefaultResolver and ARCHITECTURE.md,
 * "Modes: Loose, Strict, Passthru" — "Guardrails on fabrication"). Not a
 * TypeError, not silent, and not unbounded recursion — a clear, named stop
 * telling the person to configure that call themselves. Properties,
 * constructor, and message rendering live in FabricationLimitExceededFields,
 * shared with Integrations\PHPUnit\PHPUnitFabricationLimitExceededException
 * — see that trait's docblock and ARCHITECTURE.md's "PHPUnit integration".
 */
class FabricationLimitExceededException extends TestDoubleException
{
    use FabricationLimitExceededFields;
}
