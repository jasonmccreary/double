<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\SelfDiagnosing;
use JMac\Testing\Exceptions\UnexpectedCallFields;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to Exceptions\UnexpectedCallException.
 *
 * Extends AssertionFailedError, not UnexpectedCallException, since PHP has
 * no multiple inheritance — properties, constructor, and message come from
 * the shared UnexpectedCallFields trait instead, to avoid duplicating them
 * by hand.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory, so autoloading this file never requires
 * phpunit/phpunit to be installed.
 */
final class PHPUnitUnexpectedCallException extends AssertionFailedError implements Diagnostic
{
    use SelfDiagnosing;
    use UnexpectedCallFields;
}
