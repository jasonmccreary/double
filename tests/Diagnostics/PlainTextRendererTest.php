<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Diagnostics;

use JMac\Testing\Diagnostics\CallLimitExceededDiagnostic;
use JMac\Testing\Diagnostics\InvalidDoubleTargetDiagnostic;
use JMac\Testing\Diagnostics\ModeConfigurationDiagnostic;
use JMac\Testing\Diagnostics\PassthruAutoInstantiationDiagnostic;
use JMac\Testing\Diagnostics\PlainTextRenderer;
use JMac\Testing\Diagnostics\UnexpectedCallDiagnostic;
use JMac\Testing\Diagnostics\UnknownMethodDiagnostic;
use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Diagnostics\UnsatisfiedExpectationsDiagnostic;

final class PlainTextRendererTest extends GoldenFileTestCase
{
    private PlainTextRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PlainTextRenderer;
    }

    public function test_renders_unexpected_call(): void
    {
        $diagnostic = new UnexpectedCallDiagnostic('BookRepository', 'count', '');

        $this->assertMatchesGolden('unexpected-call', $this->renderer->render($diagnostic));
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
        $diagnostic = new UnsatisfiedExpectationsDiagnostic('foo', [$expectation]);

        $this->assertMatchesGolden('unsatisfied-expectation-with-correlation', $this->renderer->render($diagnostic));
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
        $diagnostic = new UnsatisfiedExpectationsDiagnostic('BookRepository', [$expectation]);

        $this->assertMatchesGolden('unsatisfied-expectation-without-correlation', $this->renderer->render($diagnostic));
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
        $diagnostic = new UnsatisfiedExpectationsDiagnostic('BookRepository', [$first, $second]);

        $this->assertMatchesGolden('unsatisfied-expectations-multiple', $this->renderer->render($diagnostic));
    }

    public function test_renders_call_limit_exceeded(): void
    {
        $diagnostic = new CallLimitExceededDiagnostic('BookRepository', 'delete', '1', 1, 2);

        $this->assertMatchesGolden('call-limit-exceeded', $this->renderer->render($diagnostic));
    }

    public function test_renders_unexpected_call_on_a_fabricated_double_with_provenance_note(): void
    {
        $diagnostic = new UnexpectedCallDiagnostic('Book', 'getAuthor', '', fabricated: true);

        $this->assertMatchesGolden('unexpected-call-fabricated', $this->renderer->render($diagnostic));
    }

    public function test_renders_passthru_auto_instantiation_for_an_interface(): void
    {
        $diagnostic = PassthruAutoInstantiationDiagnostic::isInterface('BookRepositoryInterface');

        $this->assertMatchesGolden('passthru-auto-instantiation-interface', $this->renderer->render($diagnostic));
    }

    public function test_renders_passthru_auto_instantiation_for_a_throwing_constructor(): void
    {
        $diagnostic = PassthruAutoInstantiationDiagnostic::constructionFailed('ConcreteLogger', new \RuntimeException('boom'));

        $this->assertMatchesGolden('passthru-auto-instantiation-construction-failed', $this->renderer->render($diagnostic));
    }

    public function test_renders_unknown_method(): void
    {
        $diagnostic = new UnknownMethodDiagnostic('BookRepositoryInterface', 'bogus');

        $this->assertMatchesGolden('unknown-method', $this->renderer->render($diagnostic));
    }

    public function test_renders_mode_configuration(): void
    {
        $diagnostic = new ModeConfigurationDiagnostic('BookRepository', 'Strict', 'Strict');

        $this->assertMatchesGolden('mode-configuration', $this->renderer->render($diagnostic));
    }

    public function test_renders_invalid_double_target_does_not_exist(): void
    {
        $diagnostic = InvalidDoubleTargetDiagnostic::doesNotExist('NoSuchClass');

        $this->assertMatchesGolden('invalid-double-target-does-not-exist', $this->renderer->render($diagnostic));
    }

    public function test_renders_invalid_double_target_is_final(): void
    {
        $diagnostic = InvalidDoubleTargetDiagnostic::isFinal('FinalLogger');

        $this->assertMatchesGolden('invalid-double-target-is-final', $this->renderer->render($diagnostic));
    }
}
