<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Strict mode: thrown immediately when a call matches no configured
 * expects()/allows() expectation — no fabrication, no defaults. Properties,
 * constructor, and message rendering live in UnexpectedCallFields, shared with
 * Integrations\PHPUnit\PHPUnitUnexpectedCallException — see that trait's
 * docblock.
 */
class UnexpectedCallException extends TestDoubleException
{
    use UnexpectedCallFields;
}
