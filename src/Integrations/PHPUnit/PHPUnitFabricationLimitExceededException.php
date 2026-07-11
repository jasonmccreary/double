<?php

declare(strict_types=1);

namespace JMac\Testing\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Exceptions\FabricationLimitExceededException;
use PHPUnit\Framework\AssertionFailedError;

/**
 * PHPUnit-specific counterpart to FabricationLimitExceededException. See
 * PHPUnitUnexpectedCallException's docblock for why this extends
 * AssertionFailedError instead of FabricationLimitExceededException, and
 * ARCHITECTURE.md's "PHPUnit integration" for the full trade-off.
 *
 * Only ever constructed from behind the class_exists(TestCase::class) guard
 * in Engine\ExceptionFactory.
 */
final class PHPUnitFabricationLimitExceededException extends AssertionFailedError implements Diagnostic
{
    public function __construct(
        public readonly string $label,
        public readonly string $method,
        public readonly string $returnType,
        public readonly int $limit,
    ) {
        parent::__construct(FabricationLimitExceededException::renderMessage($label, $method));
    }

    public function getDiagnostic(): Diagnostic
    {
        return $this;
    }
}
