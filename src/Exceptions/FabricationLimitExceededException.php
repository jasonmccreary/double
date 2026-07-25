<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Loose mode: thrown when an unconfigured call chain goes more than one
 * fabricated stand-in deep (see SafeDefaultResolver). Not a TypeError, not
 * silent, and not unbounded recursion — a clear, named stop telling the
 * person to configure that call themselves.
 */
class FabricationLimitExceededException extends TestDoubleException
{
    use FabricationLimitExceededFields;
}
