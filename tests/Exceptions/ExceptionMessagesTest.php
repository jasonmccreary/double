<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Exceptions;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\InvalidDoubleTargetException;
use JMac\Testing\Exceptions\ModeConfigurationException;
use JMac\Testing\Exceptions\PassthruAutoInstantiationException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnknownMethodException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;

final class ExceptionMessagesTest extends GoldenFileTestCase
{
    public function test_renders_unexpected_call(): void
    {
        $exception = new UnexpectedCallException('BookRepository', 'count', '');

        $this->assertMatchesGolden('unexpected-call', $exception->getMessage());
    }

    /**
     * The motivating scenario from ARCHITECTURE.md's "Correlating unsatisfied
     * expectations with actual observed calls": expects('bar')->with('baz')
     * never fires because the code under test actually called bar('Baz').
     */
    public function test_renders_unsatisfied_expectation_with_call_correlation(): void
    {
        $expectation = new UnsatisfiedExpectation(
            method: 'bar',
            description: "bar('baz') — expected exactly 1 time(s), called 0 time(s)",
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: ["'Baz'"],
        );
        $exception = new UnsatisfiedExpectationException('foo', [$expectation]);

        $this->assertMatchesGolden('unsatisfied-expectation-with-correlation', $exception->getMessage());
    }

    public function test_renders_unsatisfied_expectation_with_no_observed_calls_at_all(): void
    {
        $expectation = new UnsatisfiedExpectation(
            method: 'delete',
            description: 'delete(any arguments) — expected exactly 1 time(s), called 0 time(s)',
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: [],
        );
        $exception = new UnsatisfiedExpectationException('BookRepository', [$expectation]);

        $this->assertMatchesGolden('unsatisfied-expectation-without-correlation', $exception->getMessage());
    }

    public function test_renders_multiple_unsatisfied_expectations(): void
    {
        $first = new UnsatisfiedExpectation(
            method: 'save',
            description: 'save(any arguments) — expected exactly 1 time(s), called 0 time(s)',
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: [],
        );
        $second = new UnsatisfiedExpectation(
            method: 'delete',
            description: 'delete(any arguments) — expected at least 1 time(s), called 0 time(s)',
            expectedMin: 1,
            expectedMax: PHP_INT_MAX,
            timesCalled: 0,
            otherObservedCalls: [],
        );
        $exception = new UnsatisfiedExpectationException('BookRepository', [$first, $second]);

        $this->assertMatchesGolden('unsatisfied-expectations-multiple', $exception->getMessage());
    }

    public function test_renders_call_limit_exceeded(): void
    {
        $exception = new ExpectationCallLimitExceededException('BookRepository', 'delete', '1', 1, 2);

        $this->assertMatchesGolden('call-limit-exceeded', $exception->getMessage());
    }

    public function test_renders_unexpected_call_on_a_fabricated_double_with_provenance_note(): void
    {
        $exception = new UnexpectedCallException('Book', 'getAuthor', '', fabricated: true);

        $this->assertMatchesGolden('unexpected-call-fabricated', $exception->getMessage());
    }

    public function test_renders_passthru_auto_instantiation_for_an_interface(): void
    {
        $exception = PassthruAutoInstantiationException::isInterface('BookRepositoryInterface');

        $this->assertMatchesGolden('passthru-auto-instantiation-interface', $exception->getMessage());
    }

    public function test_renders_passthru_auto_instantiation_for_a_throwing_constructor(): void
    {
        $exception = PassthruAutoInstantiationException::constructionFailed('ConcreteLogger', new \RuntimeException('boom'));

        $this->assertMatchesGolden('passthru-auto-instantiation-construction-failed', $exception->getMessage());
    }

    public function test_renders_unknown_method(): void
    {
        $exception = new UnknownMethodException('BookRepositoryInterface', 'bogus');

        $this->assertMatchesGolden('unknown-method', $exception->getMessage());
    }

    public function test_renders_mode_configuration(): void
    {
        $exception = new ModeConfigurationException('BookRepository', 'Strict', 'Strict');

        $this->assertMatchesGolden('mode-configuration', $exception->getMessage());
    }

    public function test_renders_invalid_double_target_does_not_exist(): void
    {
        $exception = InvalidDoubleTargetException::doesNotExist('NoSuchClass');

        $this->assertMatchesGolden('invalid-double-target-does-not-exist', $exception->getMessage());
    }

    public function test_renders_invalid_double_target_is_final(): void
    {
        $exception = InvalidDoubleTargetException::isFinal('FinalLogger');

        $this->assertMatchesGolden('invalid-double-target-is-final', $exception->getMessage());
    }
}
