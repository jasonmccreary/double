<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\UnsatisfiedExpectationFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\UnsatisfiedExpectationException.
 * See PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError and gets its properties/constructor/message from a
 * shared trait (here, UnsatisfiedExpectationFields) rather than inheriting
 * UnsatisfiedExpectationException.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitUnsatisfiedExpectationException extends AssertionFailedError implements Diagnostic
{
    use SelfDiagnosing;
    use UnsatisfiedExpectationFields;
}
