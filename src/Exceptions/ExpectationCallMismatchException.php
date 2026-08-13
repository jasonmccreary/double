<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown immediately when a call is made to a method that has expects()
 * configured, but the call doesn't match any of that method's configured
 * expectations — regardless of the double's overall mode. Loose mode's
 * safe-default fallback only ever applies to methods you never mentioned at
 * all; once expects() names a method, every call to it must match one of
 * its configured expectations.
 */
class ExpectationCallMismatchException extends DoubleException
{
    use ExpectationCallMismatchFields;
}
