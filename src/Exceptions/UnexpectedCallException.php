<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Strict mode: thrown immediately when a call matches no configured
 * expects()/allows() expectation — no fabrication, no defaults.
 */
class UnexpectedCallException extends DoubleException
{
    use UnexpectedCallFields;
}
