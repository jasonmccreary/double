<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * The properties, constructor, and message for "Loose mode's fabrication
 * depth cap was hit" — shared with
 * Integrations\PHPUnit\PHPUnitFabricationLimitExceededException via a trait
 * (see UnexpectedCallFields).
 */
trait FabricationLimitExceededFields
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        // Unused by renderMessage() below — kept as fields for anything inspecting
        // the exception programmatically. The message itself deliberately says
        // less than everything the object knows.
        public readonly string $returnType,
        public readonly int $limit,
    ) {
        parent::__construct(self::renderMessage($label, $method));
    }

    public static function renderMessage(string $label, string $method): string
    {
        return sprintf(
            'Double `%s` was returned automatically. This only happens one level deep from the '
            .'original double. To respond to `%s()`, you\'ll need to configure it explicitly. '
            // $anotherDouble is a placeholder, not derived from $returnType — a real
            // instance, a further Double::for() call, anything satisfying the type
            // works equally, so naming it after $returnType would overstate how
            // prescriptive this suggestion actually is.
            .'For example: `$%s->allows(\'%s\')->returns($anotherDouble)`.',
            $label,
            $method,
            DoubleException::suggestedVariableName($label),
            $method,
        );
    }
}
