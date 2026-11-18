<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\CheckEvent;
use JMac\Testing\Diagnostics\AmbiguousExpectation;
use JMac\Testing\Diagnostics\ArgumentComparison;
use JMac\Testing\Diagnostics\Diagnostic;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Double;
use JMac\Testing\Exceptions\AmbiguousExpectationException;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\ExpectationCallMismatchException;
use JMac\Testing\Exceptions\FabricationLimitExceededException;
use JMac\Testing\Exceptions\OutOfOrderCallException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionException;
use JMac\Testing\Exceptions\UnusedAssertionException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitAmbiguousExpectationException;
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
 * its exception right before returning it — the single choke point every
 * in-scope check failure passes through, so that's the only place this
 * wiring needs to live. See CheckEvent's own docblock for exactly which
 * checks are in scope.
 *
 * The four call-time failures are also recorded on DoubleState before
 * notifying listeners, so verification still fails if the code under test
 * catches the exception.
 */
final class ExceptionFactory
{
    /**
     * @param  list<string>  $otherObservedCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function unexpectedCall(
        DoubleState $state,
        string $method,
        string $argumentsDescription,
        array $otherObservedCalls = [],
        ?array $argumentComparisons = null,
    ): Diagnostic&\Throwable {
        $label = $state->label();
        $fabricated = $state->isFabricated();

        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnexpectedCallException($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls, $argumentComparisons);
        } else {
            $exception = new UnexpectedCallException($label, $method, $argumentsDescription, $fabricated, $otherObservedCalls, $argumentComparisons);
        }

        self::appendOriginNote($exception, $state);

        $state->recordCallFailure($exception);

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    public static function expectationCallLimitExceeded(
        DoubleState $state,
        string $method,
        string $argumentsDescription,
        int $maximum,
        int $callNumber,
        int $otherMatchingExpectations = 0,
    ): Diagnostic&\Throwable {
        $label = $state->label();
        $fabricated = $state->isFabricated();

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

        self::appendOriginNote($exception, $state);

        $state->recordCallFailure($exception);

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<string>  $configuredCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function expectationCallMismatch(
        DoubleState $state,
        string $method,
        string $argumentsDescription,
        array $configuredCalls = [],
        ?array $argumentComparisons = null,
        bool $passthru = false,
    ): Diagnostic&\Throwable {
        $label = $state->label();
        $fabricated = $state->isFabricated();

        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitExpectationCallMismatchException($label, $method, $argumentsDescription, $fabricated, $configuredCalls, $argumentComparisons, $passthru);
        } else {
            $exception = new ExpectationCallMismatchException($label, $method, $argumentsDescription, $fabricated, $configuredCalls, $argumentComparisons, $passthru);
        }

        self::appendOriginNote($exception, $state);

        $state->recordCallFailure($exception);

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    public static function recordedCallFailure(string $label, Diagnostic&\Throwable $failure): Diagnostic&\Throwable
    {
        self::appendCaughtNote($failure);

        PhpUnitIntegration::registerPass();

        Double::notify(new CheckEvent($label, method: null, passed: false, failure: $failure));

        return $failure;
    }

    /**
     * Rethrowing the original exception keeps its trace pointing at the call
     * that failed, so the note is appended to that same instance rather than
     * a new one. Every call failure extends \Exception, whose $message is
     * protected, so this needs no new public API on the exception classes.
     * Idempotent, since verify() can run more than once.
     */
    private static function appendCaughtNote(\Throwable $failure): void
    {
        self::appendNote($failure, 'this was first thrown when the call was made, but something caught it.');
    }

    /**
     * A double real code created with new static runs in passthru mode with
     * nothing carried over from the double it came from, which is easy to
     * mistake for a bug when one of its own checks fails.
     *
     * @internal Also used by DoubleState::setMode().
     */
    public static function appendOriginNote(\Throwable $failure, DoubleState $state): void
    {
        if ($state->isCreatedByRealCode()) {
            self::appendNote($failure, 'real code created this double with `new static`, so it runs in passthru mode with its own expectations and call history.');
        }
    }

    /**
     * When this double is missing calls that a double of the same class,
     * created by real code with new static, actually received — the usual
     * cause is real code calling them on the new instance rather than on
     * $this. Stated only from recorded calls, never guessed.
     *
     * @param  list<string>  $methods
     */
    private static function appendRealCodeInstancesNote(\Throwable $failure, DoubleState $state, array $methods): void
    {
        $instances = $state->realCodeInstances();
        $received = [];

        foreach (array_unique($methods) as $method) {
            foreach ($instances as $instance) {
                if ($instance->callsFor($method) !== []) {
                    $received[] = '`'.$method.'`';

                    break;
                }
            }
        }

        if ($received === []) {
            return;
        }

        $methodList = count($received) === 1
            ? $received[0].' was'
            : implode(', ', array_slice($received, 0, -1)).' and '.end($received).' were';

        $doubles = count($instances) === 1
            ? sprintf('a separate `%s` double that real code created with `new static`. Its calls are', $state->label())
            : sprintf('separate `%s` doubles that real code created with `new static`. Their calls are', $state->label());

        self::appendNote($failure, sprintf("%s called on %s recorded separately from this double's.", $methodList, $doubles));
    }

    /**
     * Every failure extends \Exception, whose $message is protected, so a note
     * can be added to the same instance without new public API on the
     * exception classes. Idempotent, since verify() can run more than once.
     */
    private static function appendNote(\Throwable $failure, string $note): void
    {
        $note = "\n\nNote: ".$note;

        if (str_ends_with($failure->getMessage(), $note)) {
            return;
        }

        (function () use ($note): void {
            $this->message = rtrim($this->message, "\n").$note;
        })->call($failure);
    }

    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public static function unsatisfiedExpectation(DoubleState $state, array $expectations): Diagnostic&\Throwable
    {
        $label = $state->label();
        $fabricated = $state->isFabricated();

        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnsatisfiedExpectationException($label, $expectations, $fabricated);
        } else {
            $exception = new UnsatisfiedExpectationException($label, $expectations, $fabricated);
        }

        self::appendOriginNote($exception, $state);
        self::appendRealCodeInstancesNote($exception, $state, array_map(
            static fn (UnsatisfiedExpectation $expectation): string => $expectation->method,
            $expectations,
        ));

        Double::notify(new CheckEvent($label, method: null, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<AmbiguousExpectation>  $ambiguities
     */
    public static function ambiguousExpectations(DoubleState $state, array $ambiguities): Diagnostic&\Throwable
    {
        $label = $state->label();
        $fabricated = $state->isFabricated();

        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitAmbiguousExpectationException($label, $ambiguities, $fabricated);
        } else {
            $exception = new AmbiguousExpectationException($label, $ambiguities, $fabricated);
        }

        self::appendOriginNote($exception, $state);

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
        DoubleState $state,
        string $method,
        string $alreadyOccurredMethod,
    ): Diagnostic&\Throwable {
        $label = $state->label();
        $fabricated = $state->isFabricated();

        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitOutOfOrderCallException($label, $method, $alreadyOccurredMethod, $fabricated);
        } else {
            $exception = new OutOfOrderCallException($label, $method, $alreadyOccurredMethod, $fabricated);
        }

        self::appendOriginNote($exception, $state);

        $state->recordCallFailure($exception);

        Double::notify(new CheckEvent($label, $method, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<string>  $otherObservedCalls
     * @param  ?list<ArgumentComparison>  $argumentComparisons
     */
    public static function unsatisfiedReceivedAssertion(
        DoubleState $state,
        string $description,
        string $method = '',
        array $otherObservedCalls = [],
        ?string $argumentMismatch = null,
        ?array $argumentComparisons = null,
        bool $tooFewCalls = true,
    ): Diagnostic&\Throwable {
        $label = $state->label();
        $fabricated = $state->isFabricated();

        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnsatisfiedReceivedAssertionException($label, $description, $fabricated, $method, $otherObservedCalls, $argumentMismatch, $argumentComparisons);
        } else {
            $exception = new UnsatisfiedReceivedAssertionException($label, $description, $fabricated, $method, $otherObservedCalls, $argumentMismatch, $argumentComparisons);
        }

        self::appendOriginNote($exception, $state);

        if ($tooFewCalls && $method !== '') {
            self::appendRealCodeInstancesNote($exception, $state, [$method]);
        }

        Double::notify(new CheckEvent($label, $method === '' ? null : $method, passed: false, failure: $exception));

        return $exception;
    }

    /**
     * @param  list<string>  $calls
     */
    public static function unusedAssertion(DoubleState $state, array $calls): Diagnostic&\Throwable
    {
        $label = $state->label();
        $fabricated = $state->isFabricated();

        if (self::phpUnitIsAvailable()) {
            PhpUnitIntegration::registerPass();

            $exception = new PHPUnitUnusedAssertionException($label, $calls, $fabricated);
        } else {
            $exception = new UnusedAssertionException($label, $calls, $fabricated);
        }

        self::appendOriginNote($exception, $state);

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
