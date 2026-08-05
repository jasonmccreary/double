<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when a call matches a configured expectation, but accepting it
 * would exceed that expectation's configured maximum call count (e.g. a
 * fourth call to something configured with ->times(3), or any call at all
 * to something configured with ->never()).
 */
class ExpectationCallLimitExceededException extends DoubleException
{
    use ExpectationCallLimitExceededFields;
}
