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
 * label/method involved) rather than more prose. $anotherDouble in that fix is a placeholder
 * on purpose, not derived from $returnType: $secondLink is concrete because
 * it's the exact object throwing this exception, but what should come back
 * from the deeper call isn't pinned to any one name — a real instance, a
 * further TestDouble::for() call, anything satisfying the type all work
 * equally, so naming it after $returnType would overstate how prescriptive
 * this suggestion actually is.
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
            'Test double `%s` was returned automatically. This only happens one level deep from the '
            .'original test double. To respond to `%s()`, you\'ll need to configure it explicitly. '
            .'For example: `$%s->allows(\'%s\')->returns($anotherDouble)`.',
            $label,
            $method,
            TestDoubleException::suggestedVariableName($label),
            $method,
        );
    }
}
