<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Integrations\PHPUnit;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\FabricationLimitExceededException;
use JMac\Testing\Exceptions\OutOfOrderCallException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitFabricationLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitOutOfOrderCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SelfDescribing;
use PHPUnit\Framework\TestCase;

final class PHPUnitExceptionsTest extends TestCase
{
    public function test_unexpected_call_variant_is_an_assertion_failure_with_identical_prose(): void
    {
        $plain = new UnexpectedCallException('BookRepository', 'count', '');
        $phpunit = new PHPUnitUnexpectedCallException('BookRepository', 'count', '');

        $this->assertInstanceOf(AssertionFailedError::class, $phpunit);
        $this->assertInstanceOf(SelfDescribing::class, $phpunit);
        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame($phpunit->getMessage(), $phpunit->toString());
        $this->assertSame($phpunit, $phpunit->getDiagnostic());
    }

    /**
     * Same parity check as above, specifically with otherObservedCalls
     * populated — the newest field on this trait, and the one most likely to
     * be hand-duplicated out of sync between the two classes if it were ever
     * added outside the shared trait.
     */
    public function test_unexpected_call_variant_correlation_is_identical_prose(): void
    {
        $plain = new UnexpectedCallException('BookRepository', 'find', '456', otherObservedCalls: ['123']);
        $phpunit = new PHPUnitUnexpectedCallException('BookRepository', 'find', '456', otherObservedCalls: ['123']);

        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame(['123'], $phpunit->otherObservedCalls);
    }

    public function test_call_limit_exceeded_variant_is_an_assertion_failure_with_identical_prose(): void
    {
        $plain = new ExpectationCallLimitExceededException('BookRepository', 'delete', '1', 1, 2);
        $phpunit = new PHPUnitExpectationCallLimitExceededException('BookRepository', 'delete', '1', 1, 2);

        $this->assertInstanceOf(AssertionFailedError::class, $phpunit);
        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame($phpunit, $phpunit->getDiagnostic());
    }

    public function test_unsatisfied_expectation_variant_is_an_assertion_failure_with_identical_prose(): void
    {
        $expectation = new UnsatisfiedExpectation(
            method: 'delete',
            description: 'expected `delete(any arguments)` to be called exactly 1 time, but it was never called',
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: [],
        );

        $plain = new UnsatisfiedExpectationException('BookRepository', [$expectation]);
        $phpunit = new PHPUnitUnsatisfiedExpectationException('BookRepository', [$expectation]);

        $this->assertInstanceOf(AssertionFailedError::class, $phpunit);
        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame($phpunit, $phpunit->getDiagnostic());
    }

    public function test_fabrication_limit_exceeded_variant_is_an_assertion_failure_with_identical_prose(): void
    {
        $plain = new FabricationLimitExceededException('SecondLink', 'toThird', 'ThirdLink', 1);
        $phpunit = new PHPUnitFabricationLimitExceededException('SecondLink', 'toThird', 'ThirdLink', 1);

        $this->assertInstanceOf(AssertionFailedError::class, $phpunit);
        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame($phpunit, $phpunit->getDiagnostic());
    }

    public function test_out_of_order_call_variant_is_an_assertion_failure_with_identical_prose(): void
    {
        $plain = new OutOfOrderCallException('BookRepository', 'find', 'delete');
        $phpunit = new PHPUnitOutOfOrderCallException('BookRepository', 'find', 'delete');

        $this->assertInstanceOf(AssertionFailedError::class, $phpunit);
        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame($phpunit, $phpunit->getDiagnostic());
    }

    public function test_unsatisfied_received_assertion_variant_is_an_assertion_failure_with_identical_prose(): void
    {
        $plain = new UnsatisfiedReceivedAssertionException('BookRepository', 'expected `delete(any arguments)` to be called at least 1 time, but it was never called');
        $phpunit = new PHPUnitUnsatisfiedReceivedAssertionException('BookRepository', 'expected `delete(any arguments)` to be called at least 1 time, but it was never called');

        $this->assertInstanceOf(AssertionFailedError::class, $phpunit);
        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame($phpunit, $phpunit->getDiagnostic());
    }

    public function test_unsatisfied_received_assertion_variant_with_correlation_is_an_assertion_failure_with_identical_prose(): void
    {
        $plain = new UnsatisfiedReceivedAssertionException(
            'AnalyticsHelper',
            "expected `event('nope')` to be called at least 1 time, but it was called 0 times",
            method: 'event',
            otherObservedCalls: ["'found usages of renamed pagination methods'"],
        );
        $phpunit = new PHPUnitUnsatisfiedReceivedAssertionException(
            'AnalyticsHelper',
            "expected `event('nope')` to be called at least 1 time, but it was called 0 times",
            method: 'event',
            otherObservedCalls: ["'found usages of renamed pagination methods'"],
        );

        $this->assertInstanceOf(AssertionFailedError::class, $phpunit);
        $this->assertSame($plain->getMessage(), $phpunit->getMessage());
        $this->assertSame($phpunit, $phpunit->getDiagnostic());
    }
}
