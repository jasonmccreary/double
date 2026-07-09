<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * Picks between a plain TestDoubleException and its PHPUnit-specific
 * counterpart via a runtime class_exists(TestCase::class) check, so
 * ProxyBehavior and TestDouble never need to know PHPUnit exists — see
 * ARCHITECTURE.md, "PHPUnit integration."
 *
 * The Integrations\PHPUnit class names below are only ever referenced
 * inside the guarded branch of each method. A `use` import alone never
 * triggers autoloading, so this file itself is always safe to autoload;
 * the `new PHPUnitXxxException(...)` calls are the only thing that would
 * ever load those classes, and they only run once class_exists() has
 * already confirmed phpunit/phpunit is present.
 */
final class ExceptionFactory
{
    public static function unexpectedCall(
        string $label,
        string $method,
        string $argumentsDescription,
        bool $fabricated,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            return new PHPUnitUnexpectedCallException($label, $method, $argumentsDescription, $fabricated);
        }

        return new UnexpectedCallException($label, $method, $argumentsDescription, $fabricated);
    }

    public static function expectationCallLimitExceeded(
        string $label,
        string $method,
        string $argumentsDescription,
        int $maximum,
        int $callNumber,
        bool $fabricated,
    ): Diagnostic&\Throwable {
        if (self::phpUnitIsAvailable()) {
            return new PHPUnitExpectationCallLimitExceededException(
                $label,
                $method,
                $argumentsDescription,
                $maximum,
                $callNumber,
                $fabricated,
            );
        }

        return new ExpectationCallLimitExceededException($label, $method, $argumentsDescription, $maximum, $callNumber, $fabricated);
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

    private static function phpUnitIsAvailable(): bool
    {
        return class_exists(TestCase::class);
    }
}
