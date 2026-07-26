<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\ExpectationCallLimitExceededException.
 * See PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError and gets its properties/constructor/message from a
 * shared trait (here, ExpectationCallLimitExceededFields) rather than
 * inheriting ExpectationCallLimitExceededException.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitExpectationCallLimitExceededException extends AssertionFailedError implements Diagnostic
{
    use ExpectationCallLimitExceededFields;
    use SelfDiagnosing;
}
