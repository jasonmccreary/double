<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\OutOfOrderCallFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\OutOfOrderCallException. See
 * PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError rather than OutOfOrderCallException, and why the
 * shared fields/constructor/message live in the OutOfOrderCallFields trait
 * instead.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory — never referenced unconditionally elsewhere,
 * so autoloading this file never requires phpunit/phpunit to be installed.
 */
final class PHPUnitOutOfOrderCallException extends AssertionFailedError implements Diagnostic
{
    use OutOfOrderCallFields;
    use SelfDiagnosing;
}
