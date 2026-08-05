<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown by a received() assertion chain (see Engine\ReceivedAssertion) when
 * the calls actually recorded, filtered by any with() constraint, fall
 * outside the chain's configured bounds — too few or too many. Both
 * directions render through the same message (MethodExpectation::describe()'s
 * "expected X, called Y" phrasing) rather than splitting into a separate
 * "too many" variant the way expects()/allows() do: a received() assertion
 * isn't caught live mid-call, so by the time __destruct() checks it, every
 * call has already happened.
 */
class UnsatisfiedReceivedAssertionException extends DoubleException
{
    use UnsatisfiedReceivedAssertionFields;
}
