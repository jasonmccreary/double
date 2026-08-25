<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\CheckEvent;
use JMac\Testing\Diagnostics\ArgumentComparison;
use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Double;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\ExpectationCallMismatchException;
use JMac\Testing\Exceptions\FabricationLimitExceededException;
use JMac\Testing\Exceptions\OutOfOrderCallException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionException;
use JMac\Testing\Exceptions\UnusedAssertionException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallMismatchException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitFabricationLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitOutOfOrderCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnusedAssertionException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Picks between a plain DoubleException and its PHPUnit-specific
 * counterpart via a runtime class_exists(TestCase::class) check, so
 * ProxyBehavior and Double never need to know PHPUnit exists.
 *
 * Every PHPUnit-specific exception built here extends AssertionFailedError,
 * but merely throwing one doesn't touch PHPUnit's own assertion counter —
 * that only increments via a real Assert::*() call. Left alone, a test that
 * fails this way would be flagged risky ("did not perform any assertions")
 * on top of failing. PhpUnitIntegration::registerPass() right before each
 * one is constructed closes that gap, the same sanctioned integration point
 * the success paths (verify(), unused(), received()) already use.
 *
 * Every method here except fabricationLimitExceeded() (an engine safety
 * valve, not a check the test authored) also calls Double::notify() with
 * the exception it just built, right before returning it — the single
 * choke point every in-scope check failure passes through, so that's the
 * only place this wiring needs to live. See CheckEvent's own docblock for
 * exactly which checks are in scope.
 */
final class ExceptionFactory
{
    /**
     * @param  list<string>  $otherObservedCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function unexpectedCall(
        string $label,
        string $method,
        string $argumentsDescription,
        bool $fabricated,
        array $otherObservedCalls = [],
        ?array $argumentComparisons = null,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnexpectedCallException($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls, $argumentComparisons);
        } else {
            $exception = new UnexpectedCallException($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls, $argumentComparisons);
        }

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    public static function expectationCallLimitExceeded(
        string $label,
        string $method,
        string $argumentsDescription,
        int $maximum,
        int $callNumber,
        bool $fabricated,
        int $otherMatchingExpectations = 0,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitExpectationCallLimitExceededException(
                $label,
                $method,
                $argumentsDescription,
                $maximum,
                $callNumber,
                $fabricated,
                $otherMatchingExpectations,
            );
        } else {
            $exception = new ExpectationCallLimitExceededException(
                $label,
                $method,
                $argumentsDescription,
                $maximum,
                $callNumber,
                $fabricated,
                $otherMatchingExpectations,
            );
        }

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<string>  $configuredCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function expectationCallMismatch(
        string $label,
        string $method,
        string $argumentsDescription,
        bool $fabricated,
        array $configuredCalls = [],
        ?array $argumentComparisons = null,
        bool $passthru = false,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitExpectationCallMismatchException($label, $method, $argumentsDescription, $fabricated, $configuredCalls, $argumentComparisons, $passthru);
        } else {
            $exception = new ExpectationCallMismatchException($label, $method, $argumentsDescription, $fabricated, $configuredCalls, $argumentComparisons, $passthru);
        }

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public static function unsatisfiedExpectation(string $label, array $expectations, bool $fabricated): Diagnostic&\Throwable
    {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnsatisfiedExpectationException($label, $expectations, $fabricated);
        } else {
            $exception = new UnsatisfiedExpectationException($label, $expectations, $fabricated);
        }

        Double::notify(new CheckEvent($label, method: null, passed: false, failure: $exception));

        return $exception;
    }

    public static function fabricationLimitExceeded(
        string $label,
        string $method,
        string $returnType,
        int $limit,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            return new PHPUnitFabricationLimitExceededException($label, $method, $returnType, $limit);
        }

        return new FabricationLimitExceededException($label, $method, $returnType, $limit);
    }

    public static function outOfOrderCall(
        string $label,
        string $method,
        string $alreadyOccurredMethod,
        bool $fabricated,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitOutOfOrderCallException($label, $method, $alreadyOccurredMethod, $fabricated);
        } else {
            $exception = new OutOfOrderCallException($label, $method, $alreadyOccurredMethod, $fabricated);
        }

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<string>  $otherObservedCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function unsatisfiedReceivedAssertion(
        string $label,
        string $description,
        bool $fabricated,
        string $method = '',
        array $otherObservedCalls = [],
        ?string $argumentMismatch = null,
        ?array $argumentComparisons = null,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnsatisfiedReceivedAssertionException($label, $description, $fabricated, $method, $otherObservedCalls, $argumentMismatch, $argumentComparisons);
        } else {
            $exception = new UnsatisfiedReceivedAssertionException($label, $description, $fabricated, $method, $otherObservedCalls, $argumentMismatch, $argumentComparisons);
        }

        Double::notify(new CheckEvent($label, $method === '' ? null : $method, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<string>  $calls
     */
    public static function unusedAssertion(string $label, array $calls, bool $fabricated): Diagnostic&\Throwable
    {
        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnusedAssertionException($label, $calls, $fabricated);
        } else {
            $exception = new UnusedAssertionException($label, $calls, $fabricated);
        }

        Double::notify(new CheckEvent($label, method: null, passed: false, failure: $exception));

        return $exception;
    }

    // A `use` import alone never triggers autoloading, so this file stays
    // safe to autoload regardless — the `new PHPUnitXxxException(...)` calls
    // above are the only thing that would load those classes, and they only
    // run once this has already confirmed phpunit/phpunit is present.
    private static function phpUnitIsAvailable(): bool
    {
        return class_exists(TestCase::class);
    }
}
