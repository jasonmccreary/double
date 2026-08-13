<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\ExceptionFactory;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitFabricationLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitOutOfOrderCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * phpunit/phpunit is always present while this suite itself runs (it's the
 * test runner), so ExceptionFactory's class_exists(TestCase::class) branch
 * is always true here — these assertions prove the switch actually picks
 * the PHPUnit variant rather than merely assuming it does. The "PHPUnit
 * unavailable" branch is proven separately in CI — see
 * tests/smoke/without-phpunit.php.
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

    public function test_fabrication_limit_exceeded_picks_the_phpunit_variant(): void
    {
        $exception = ExceptionFactory::fabricationLimitExceeded('SecondLink', 'toThird', 'ThirdLink', 1);

        $this->assertInstanceOf(PHPUnitFabricationLimitExceededException::class, $exception);
    }

    public function test_out_of_order_call_picks_the_phpunit_variant(): void
    {
        $exception = ExceptionFactory::outOfOrderCall('BookRepository', 'find', 'delete', false);

        $this->assertInstanceOf(PHPUnitOutOfOrderCallException::class, $exception);
    }

    /**
     * Every one of these is an AssertionFailedError, but merely constructing
     * one doesn't touch PHPUnit's own assertion counter — only a real
     * Assert::*() call does. Without registerPass(), a test whose only
     * "check" is one of these exceptions would be flagged risky ("did not
     * perform any assertions") on top of failing.
     */
    public function test_every_exception_registers_a_phpunit_pass_on_construction(): void
    {
        $factoryCalls = [
            fn () => ExceptionFactory::unexpectedCall('BookRepository', 'count', '', false),
            fn () => ExceptionFactory::expectationCallLimitExceeded('BookRepository', 'delete', '1', 1, 2, false),
            fn () => ExceptionFactory::expectationCallMismatch('BookRepository', 'find', '456', false),
            fn () => ExceptionFactory::unsatisfiedExpectation('BookRepository', [], false),
            fn () => ExceptionFactory::fabricationLimitExceeded('SecondLink', 'toThird', 'ThirdLink', 1),
            fn () => ExceptionFactory::outOfOrderCall('BookRepository', 'find', 'delete', false),
            fn () => ExceptionFactory::unsatisfiedReceivedAssertion('BookRepository', 'was never called', false),
            fn () => ExceptionFactory::unusedAssertion('BookRepository', [], false),
        ];

        foreach ($factoryCalls as $factoryCall) {
            $before = Assert::getCount();

            $factoryCall();

            $this->assertGreaterThan($before, Assert::getCount());
        }
    }
}
