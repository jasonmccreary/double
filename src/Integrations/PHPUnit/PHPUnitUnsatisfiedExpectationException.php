<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to UnsatisfiedExpectationException. See
 * PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError instead of UnsatisfiedExpectationException, and
 * ARCHITECTURE.md's "PHPUnit integration" for the full trade-off.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitUnsatisfiedExpectationException extends AssertionFailedError implements Diagnostic
{
    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public function __construct(
        public readonly string $label,
        public readonly array $expectations,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(UnsatisfiedExpectationException::renderMessage($label, $expectations, $fabricated));
    }

    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }
}
