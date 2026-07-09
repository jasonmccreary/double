<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\ExceptionFactory;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use PHPUnit\Framework\TestCase;

/**
 * phpunit/phpunit is always present while this suite itself runs (it's the
 * test runner), so ExceptionFactory's class_exists(TestCase::class) branch
 * is always true here — these assertions prove the switch actually picks
 * the PHPUnit variant rather than merely assuming it does. The "PHPUnit
 * unavailable" branch is proven separately in CI, per ARCHITECTURE.md's
 * "PHPUnit integration" mitigations.
 */
final class ExceptionFactoryTest extends TestCase
{
    public function test_unexpected_call_picks_the_phpunit_variant(): void
    {
        $exception = ExceptionFactory::unexpectedCall('BookRepository', 'count', '', false);

        $this->assertInstanceOf(PHPUnitUnexpectedCallException::class, $exception);
    }

    public function test_expectation_call_limit_exceeded_picks_the_phpunit_variant(): void
    {
        $exception = ExceptionFactory::expectationCallLimitExceeded('BookRepository', 'delete', '1', 1, 2, false);

        $this->assertInstanceOf(PHPUnitExpectationCallLimitExceededException::class, $exception);
    }

    public function test_unsatisfied_expectation_picks_the_phpunit_variant(): void
    {
        $exception = ExceptionFactory::unsatisfiedExpectation('BookRepository', [], false);

        $this->assertInstanceOf(PHPUnitUnsatisfiedExpectationException::class, $exception);
    }
}
