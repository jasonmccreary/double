<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\FabricationLimitExceededFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\FabricationLimitExceededException.
 * See PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError and gets its properties/constructor/message from a
 * shared trait (here, FabricationLimitExceededFields) rather than
 * inheriting FabricationLimitExceededException.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitFabricationLimitExceededException extends AssertionFailedError implements Diagnostic
{
    use FabricationLimitExceededFields;
    use SelfDiagnosing;
}
