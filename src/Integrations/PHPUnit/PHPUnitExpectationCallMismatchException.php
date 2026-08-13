<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\ExpectationCallMismatchFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\ExpectationCallMismatchException.
 *
 * Extends AssertionFailedError, not ExpectationCallMismatchException, since
 * PHP has no multiple inheritance — properties, constructor, and message
 * come from the shared ExpectationCallMismatchFields trait instead, to avoid
 * duplicating them by hand.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory, so autoloading this file never requires
 * phpunit/phpunit to be installed.
 */
final class PHPUnitExpectationCallMismatchException extends AssertionFailedError implements Diagnostic
{
    use ExpectationCallMismatchFields;
    use SelfDiagnosing;
}
