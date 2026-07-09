<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Exceptions\UnexpectedCallException;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to UnexpectedCallException.
 *
 * Extends AssertionFailedError rather than UnexpectedCallException: PHP has
 * no multiple inheritance, and AssertionFailedError is specifically the
 * class PHPUnit checks for (see ThrowableToStringMapper and the test
 * runner's result collector) to decide whether a thrown exception counts as
 * a *failure* or a plain *error* — that bucketing is the entire reason this
 * class exists, so it wins over preserving `instanceof
 * UnexpectedCallException`. Renders byte-identical prose to the plain
 * exception via the shared static UnexpectedCallException::renderMessage()
 * instead of duplicating the sprintf. See ARCHITECTURE.md's "PHPUnit
 * integration" for the full trade-off.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory — never referenced unconditionally elsewhere,
 * so autoloading this file never requires phpunit/phpunit to be installed.
 */
final class PHPUnitUnexpectedCallException extends AssertionFailedError implements Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(UnexpectedCallException::renderMessage($label, $method, $argumentsDescription, $fabricated));
    }

    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }
}
