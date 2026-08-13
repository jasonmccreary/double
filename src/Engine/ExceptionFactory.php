<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Diagnostics\ArgumentComparison;
use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\FabricationLimitExceededException;
use JMac\Testing\Exceptions\OutOfOrderCallException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionException;
use JMac\Testing\Exceptions\UnusedAssertionException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
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
            return new PHPUnitUnexpectedCallException($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls, $argumentComparisons);
        }

        return new UnexpectedCallException($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls, $argumentComparisons);
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
            return new PHPUnitExpectationCallLimitExceededException(
                $label,
                $method,
                $argumentsDescription,
                $maximum,
                $callNumber,
                $fabricated,
                $otherMatchingExpectations,
            );
        }

        return new ExpectationCallLimitExceededException(
            $label,
            $method,
            $argumentsDescription,
            $maximum,
            $callNumber,
            $fabricated,
            $otherMatchingExpectations,
        );
    }

    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public static function unsatisfiedExpectation(string $label, array $expectations, bool $fabricated): Diagnostic&\Throwable
    {
        if (self::phpUnitIsAvailable()) {
            return new PHPUnitUnsatisfiedExpectationException($label, $expectations, $fabricated);
        }

        return new UnsatisfiedExpectationException($label, $expectations, $fabricated);
    }

    public static function fabricationLimitExceeded(
        string $label,
        string $method,
        string $returnType,
        int $limit,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
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
            return new PHPUnitOutOfOrderCallException($label, $method, $alreadyOccurredMethod, $fabricated);
        }

        return new OutOfOrderCallException($label, $method, $alreadyOccurredMethod, $fabricated);
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
            return new PHPUnitUnsatisfiedReceivedAssertionException($label, $description, $fabricated, $method, $otherObservedCalls, $argumentMismatch, $argumentComparisons);
        }

        return new UnsatisfiedReceivedAssertionException($label, $description, $fabricated, $method, $otherObservedCalls, $argumentMismatch, $argumentComparisons);
    }

    /**
     * @param  list<string>  $calls
     */
    public static function unusedAssertion(string $label, array $calls, bool $fabricated): Diagnostic&\Throwable
    {
        if (self::phpUnitIsAvailable()) {
            return new PHPUnitUnusedAssertionException($label, $calls, $fabricated);
        }

        return new UnusedAssertionException($label, $calls, $fabricated);
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
