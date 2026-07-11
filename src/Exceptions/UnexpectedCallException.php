<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Strict mode: thrown immediately when a call matches no configured
 * expects()/allows() expectation. See ARCHITECTURE.md, "Modes: Loose,
 * Strict, Passthru" — "Any unmatched call throws immediately... no
 * fabrication, no defaults." Properties, constructor, and message rendering
 * live in UnexpectedCallFields, shared with
 * Integrations\PHPUnit\PHPUnitUnexpectedCallException — see that trait's
 * docblock and ARCHITECTURE.md's "PHPUnit integration".
 */
class UnexpectedCallException extends TestDoubleException
{
    use UnexpectedCallFields;
}
