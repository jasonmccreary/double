<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to ExpectationCallLimitExceededException. See
 * PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError instead of ExpectationCallLimitExceededException, and
 * ARCHITECTURE.md's "PHPUnit integration" for the full trade-off.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitExpectationCallLimitExceededException extends AssertionFailedError implements Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly int $maximum,
        public readonly int $callNumber,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(ExpectationCallLimitExceededException::renderMessage(
            $label,
            $method,
            $argumentsDescription,
            $maximum,
            $callNumber,
            $fabricated,
        ));
    }

    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }
}
