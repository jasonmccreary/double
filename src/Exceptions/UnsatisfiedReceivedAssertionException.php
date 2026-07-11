<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown by a received() assertion chain (see Engine\ReceivedAssertion) when
 * the calls actually recorded for that method, filtered by any with()
 * constraint, fall outside the bounds the chain configured — either too few
 * (the default "at least once", or an explicit times()/atLeastOnce() that
 * wasn't met) or too many (never()/times() exceeded). Both directions render
 * through the same message, using MethodExpectation::describe()'s existing
 * "expected X, called Y" phrasing — there is no separate "too many" variant
 * the way ExpectationCallLimitExceededException is separate from
 * UnsatisfiedExpectationException for expects()/allows(), because a
 * received() assertion isn't caught live mid-call the way those are: by the
 * time it's checked, in __destruct(), every call it's evaluating already
 * happened. Properties, constructor, and message rendering live in
 * UnsatisfiedReceivedAssertionFields, shared with
 * Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException — see
 * that trait's docblock and ARCHITECTURE.md's "PHPUnit integration".
 */
class UnsatisfiedReceivedAssertionException extends TestDoubleException
{
    use UnsatisfiedReceivedAssertionFields;
}
