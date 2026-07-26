<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\UnusedAssertionFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\UnusedAssertionException.
 * See PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError and gets its properties/constructor/message from a
 * shared trait (here, UnusedAssertionFields) rather than inheriting
 * UnusedAssertionException.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitUnusedAssertionException extends AssertionFailedError implements Diagnostic
{
    use SelfDiagnosing;
    use UnusedAssertionFields;
}
