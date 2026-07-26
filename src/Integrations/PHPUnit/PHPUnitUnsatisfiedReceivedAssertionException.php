<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\UnsatisfiedReceivedAssertionException.
 * See PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError and gets its properties/constructor/message from a
 * shared trait (here, UnsatisfiedReceivedAssertionFields) rather than
 * inheriting UnsatisfiedReceivedAssertionException.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitUnsatisfiedReceivedAssertionException extends AssertionFailedError implements Diagnostic
{
    use SelfDiagnosing;
    use UnsatisfiedReceivedAssertionFields;
}
