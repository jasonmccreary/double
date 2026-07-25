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
 * Extends AssertionFailedError rather than UnexpectedCallException: PHP has
 * no multiple inheritance, and AssertionFailedError is specifically the
 * class PHPUnit checks for (see ThrowableToStringMapper and the test
 * runner's result collector) to decide whether a thrown exception counts as
 * a *failure* or a plain *error* — that bucketing is the entire reason this
 * class exists, so it wins over preserving `instanceof
 * UnexpectedCallException`. Properties, constructor, and message rendering
 * come from the shared UnexpectedCallFields trait (see its docblock for how
 * that avoids duplicating them by hand) rather than from inheriting
 * UnexpectedCallException.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory — never referenced unconditionally elsewhere,
 * so autoloading this file never requires phpunit/phpunit to be installed.
 */
final class PHPUnitUnexpectedCallException extends AssertionFailedError implements Diagnostic
{
    use SelfDiagnosing;
    use UnexpectedCallFields;
}
