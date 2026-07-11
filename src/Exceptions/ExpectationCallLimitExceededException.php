<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown when a call matches a configured expectation, but accepting it
 * would exceed that expectation's configured maximum call count (e.g. a
 * fourth call to something configured with ->times(3), or any call at all
 * to something configured with ->never()). Properties, constructor, and
 * message rendering live in ExpectationCallLimitExceededFields, shared with
 * Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException — see
 * that trait's docblock and ARCHITECTURE.md's "PHPUnit integration".
 */
class ExpectationCallLimitExceededException extends TestDoubleException
{
    use ExpectationCallLimitExceededFields;
}
