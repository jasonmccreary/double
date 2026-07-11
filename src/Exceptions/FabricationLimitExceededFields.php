<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * The properties, constructor, and message for "Loose mode's fabrication
 * depth cap was hit" — shared, by both FabricationLimitExceededException
 * and Integrations\PHPUnit\PHPUnitFabricationLimitExceededException. See
 * UnexpectedCallFields's docblock for why a trait, and why
 * TestDoubleException:: rather than self:: for the shared prose helper.
 *
 * renderMessage() takes only $label/$method: $returnType and $limit are
 * kept as stored fields for anything inspecting the exception
 * programmatically (see TestDoubleException's getDiagnostic()), but the
 * message itself is deliberately shorter than "everything the object
 * knows," and leads with a concrete, pasteable fix (built from the actual
 * label/method involved) rather than more prose — see ARCHITECTURE.md,
 * "Guardrails on fabrication."
 */
trait FabricationLimitExceededFields
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $returnType,
        public readonly int $limit,
    ) {
        parent::__construct(self::renderMessage($label, $method));
    }

    public static function renderMessage(string $label, string $method): string
    {
        return sprintf(
            'Test double "%s" only fabricates one call chain deep — configure "%s()" '
            .'explicitly: $%s->allows(\'%s\')->returns(...);',
            $label,
            $method,
            TestDoubleException::suggestedVariableName($label),
            $method,
        );
    }
}
