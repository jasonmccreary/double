<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Exceptions;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\FabricationLimitExceededException;
use JMac\Testing\Exceptions\InvalidDoubleTargetException;
use JMac\Testing\Exceptions\MagicMethodException;
use JMac\Testing\Exceptions\ModeConfigurationException;
use JMac\Testing\Exceptions\OutOfOrderCallException;
use JMac\Testing\Exceptions\PassthruAutoInstantiationException;
use JMac\Testing\Exceptions\ReservedNameCollisionException;
use JMac\Testing\Exceptions\StaticMethodException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnknownMethodException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionException;

final class ExceptionMessagesTest extends GoldenFileTestCase
{
    public function test_renders_unexpected_call(): void
    {
        $exception = new UnexpectedCallException('BookRepository', 'count', '');

        $this->assertMatchesGolden('unexpected-call', $exception->getMessage());
    }

    /**
     * The motivating scenario for call correlation: expects('bar')->with('baz')
     * never fires because the code under test actually called bar('Baz').
     */
    public function test_renders_unsatisfied_expectation_with_call_correlation(): void
    {
        $expectation = new UnsatisfiedExpectation(
            method: 'bar',
            description: "expected `bar('baz')` to be called exactly 1 time, but it was never called",
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: ["'Baz'"],
        );
        $exception = new UnsatisfiedExpectationException('foo', [$expectation]);

        $this->assertMatchesGolden('unsatisfied-expectation-with-correlation', $exception->getMessage());
    }

    /**
     * More than three other observed calls collapses to "and N more" rather
     * than listing every one — see CallListFormatter; this cap exists on
     * both correlation features, not just the unexpected-call one.
     */
    public function test_renders_unsatisfied_expectation_with_capped_call_correlation(): void
    {
        $expectation = new UnsatisfiedExpectation(
            method: 'bar',
            description: 'expected `bar(6)` to be called exactly 1 time, but it was never called',
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: ['1', '2', '3', '4'],
        );
        $exception = new UnsatisfiedExpectationException('foo', [$expectation]);

        $this->assertMatchesGolden('unsatisfied-expectation-with-correlation-capped', $exception->getMessage());
    }

    public function test_renders_unsatisfied_expectation_with_no_observed_calls_at_all(): void
    {
        $expectation = new UnsatisfiedExpectation(
            method: 'delete',
            description: 'expected `delete(any arguments)` to be called exactly 1 time, but it was never called',
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
            description: 'expected `save(any arguments)` to be called exactly 1 time, but it was never called',
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: [],
        );
        $second = new UnsatisfiedExpectation(
            method: 'delete',
            description: 'expected `delete(any arguments)` to be called at least 1 time, but it was never called',
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

    public function test_renders_unknown_method_with_a_suggestion(): void
    {
        $exception = new UnknownMethodException('BookRepositoryInterface', 'sav', suggestion: 'save');

        $this->assertMatchesGolden('unknown-method-with-suggestion', $exception->getMessage());
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

    public function test_renders_invalid_double_target_has_abstract_static_method(): void
    {
        $exception = InvalidDoubleTargetException::hasAbstractStaticMethod('StaticMethodInterface', 'make');

        $this->assertMatchesGolden('invalid-double-target-has-abstract-static-method', $exception->getMessage());
    }

    public function test_renders_invalid_double_target_has_abstract_magic_method(): void
    {
        $exception = InvalidDoubleTargetException::hasAbstractMagicMethod('MagicMethodInterface', '__toString');

        $this->assertMatchesGolden('invalid-double-target-has-abstract-magic-method', $exception->getMessage());
    }

    /**
     * Plain strings in, no real PHP 8.4 property-hook syntax involved — this
     * renders fine on every supported PHP version, unlike the ClassGenerator
     * regression tests that actually declare a hooked property fixture.
     */
    public function test_renders_invalid_double_target_has_abstract_property_hook(): void
    {
        $exception = InvalidDoubleTargetException::hasAbstractPropertyHook('HookedPropertyInterface', 'displayName');

        $this->assertMatchesGolden('invalid-double-target-has-abstract-property-hook', $exception->getMessage());
    }

    public function test_renders_static_method(): void
    {
        $exception = new StaticMethodException('HasStaticMethod', 'make');

        $this->assertMatchesGolden('static-method', $exception->getMessage());
    }

    public function test_renders_magic_method(): void
    {
        $exception = new MagicMethodException('HasMagicMethod', '__toString');

        $this->assertMatchesGolden('magic-method', $exception->getMessage());
    }

    public function test_renders_out_of_order_call(): void
    {
        $exception = new OutOfOrderCallException('Connection', 'open', 'close');

        $this->assertMatchesGolden('out-of-order-call', $exception->getMessage());
    }

    public function test_renders_unexpected_call_with_arguments(): void
    {
        $exception = new UnexpectedCallException('BookRepository', 'save', "5, 'Alice'");

        $this->assertMatchesGolden('unexpected-call-with-arguments', $exception->getMessage());
    }

    /**
     * The symmetric extension: the same "was already observed
     * elsewhere" fact the verify() path already shows, mirrored onto an
     * unexpected call that matched no configured expectation.
     */
    public function test_renders_unexpected_call_with_correlation(): void
    {
        $exception = new UnexpectedCallException('BookRepository', 'find', '456', otherObservedCalls: ['123']);

        $this->assertMatchesGolden('unexpected-call-with-correlation', $exception->getMessage());
    }

    /**
     * Both correlation features share CallListFormatter's cap — more than
     * three prior calls collapses to "and N more" instead of a wall of text,
     * which would defeat its own purpose once a method's been called many
     * times legitimately (e.g. once per loop iteration with a different id).
     */
    public function test_renders_unexpected_call_with_capped_correlation(): void
    {
        $exception = new UnexpectedCallException(
            'BookRepository',
            'find',
            '6',
            otherObservedCalls: ['1', '2', '3', '4', '5'],
        );

        $this->assertMatchesGolden('unexpected-call-with-correlation-capped', $exception->getMessage());
    }

    public function test_renders_unsatisfied_expectation_on_a_fabricated_double(): void
    {
        $expectation = new UnsatisfiedExpectation(
            method: 'save',
            description: 'expected `save(any arguments)` to be called exactly 1 time, but it was never called',
            expectedMin: 1,
            expectedMax: 1,
            timesCalled: 0,
            otherObservedCalls: [],
        );
        $exception = new UnsatisfiedExpectationException('SecondLink', [$expectation], fabricated: true);

        $this->assertMatchesGolden('unsatisfied-expectation-fabricated', $exception->getMessage());
    }

    public function test_renders_call_limit_exceeded_on_a_fabricated_double(): void
    {
        $exception = new ExpectationCallLimitExceededException('SecondLink', 'delete', '1', 1, 2, fabricated: true);

        $this->assertMatchesGolden('call-limit-exceeded-fabricated', $exception->getMessage());
    }

    public function test_renders_fabrication_limit_exceeded(): void
    {
        $exception = new FabricationLimitExceededException('SecondLink', 'toThird', 'ThirdLink', 1);

        $this->assertMatchesGolden('fabrication-limit-exceeded', $exception->getMessage());
    }

    public function test_renders_unknown_method_on_a_fabricated_double(): void
    {
        $exception = new UnknownMethodException('BookRepositoryInterface', 'bogus', fabricated: true);

        $this->assertMatchesGolden('unknown-method-fabricated', $exception->getMessage());
    }

    public function test_renders_unknown_method_on_a_fabricated_double_with_a_suggestion(): void
    {
        $exception = new UnknownMethodException('BookRepositoryInterface', 'sav', fabricated: true, suggestion: 'save');

        $this->assertMatchesGolden('unknown-method-fabricated-with-suggestion', $exception->getMessage());
    }

    public function test_renders_mode_configuration_on_a_fabricated_double(): void
    {
        $exception = new ModeConfigurationException('SecondLink', 'Strict', 'Strict', fabricated: true);

        $this->assertMatchesGolden('mode-configuration-fabricated', $exception->getMessage());
    }

    public function test_renders_invalid_double_target_must_be_interface(): void
    {
        $exception = InvalidDoubleTargetException::mustBeInterface('Book');

        $this->assertMatchesGolden('invalid-double-target-must-be-interface', $exception->getMessage());
    }

    public function test_renders_invalid_double_target_duplicate_target(): void
    {
        $exception = InvalidDoubleTargetException::duplicateTarget('LoggerInterface');

        $this->assertMatchesGolden('invalid-double-target-duplicate-target', $exception->getMessage());
    }

    public function test_renders_static_method_on_a_fabricated_double(): void
    {
        $exception = new StaticMethodException('HasStaticMethod', 'make', fabricated: true);

        $this->assertMatchesGolden('static-method-fabricated', $exception->getMessage());
    }

    public function test_renders_magic_method_on_a_fabricated_double(): void
    {
        $exception = new MagicMethodException('HasMagicMethod', '__toString', fabricated: true);

        $this->assertMatchesGolden('magic-method-fabricated', $exception->getMessage());
    }

    public function test_renders_out_of_order_call_on_a_fabricated_double(): void
    {
        $exception = new OutOfOrderCallException('SecondLink', 'open', 'close', fabricated: true);

        $this->assertMatchesGolden('out-of-order-call-fabricated', $exception->getMessage());
    }

    public function test_renders_unsatisfied_received_assertion(): void
    {
        $exception = new UnsatisfiedReceivedAssertionException(
            'BookRepository',
            'expected `delete(any arguments)` to be called at least 1 time, but it was never called',
        );

        $this->assertMatchesGolden('unsatisfied-received-assertion', $exception->getMessage());
    }

    public function test_renders_unsatisfied_received_assertion_on_a_fabricated_double(): void
    {
        $exception = new UnsatisfiedReceivedAssertionException(
            'SecondLink',
            'expected `save(any arguments)` to never be called, but it was called 1 time',
            fabricated: true,
        );

        $this->assertMatchesGolden('unsatisfied-received-assertion-fabricated', $exception->getMessage());
    }

    /**
     * The motivating scenario, mirrored from
     * test_renders_unsatisfied_expectation_with_call_correlation: a
     * received('event')->with(...) assertion fails on argument mismatch, even
     * though the method really was called — just with something else.
     */
    public function test_renders_unsatisfied_received_assertion_with_call_correlation(): void
    {
        $exception = new UnsatisfiedReceivedAssertionException(
            'AnalyticsHelper',
            "expected `event('this text does not match what was actually logged')` to be called at least 1 time, but it was called 0 times",
            method: 'event',
            otherObservedCalls: ["'found usages of renamed pagination methods'"],
        );

        $this->assertMatchesGolden('unsatisfied-received-assertion-with-correlation', $exception->getMessage());
    }

    public function test_renders_unsatisfied_received_assertion_with_capped_call_correlation(): void
    {
        $exception = new UnsatisfiedReceivedAssertionException(
            'AnalyticsHelper',
            'expected `event(6)` to be called at least 1 time, but it was called 0 times',
            method: 'event',
            otherObservedCalls: ['1', '2', '3', '4'],
        );

        $this->assertMatchesGolden('unsatisfied-received-assertion-with-correlation-capped', $exception->getMessage());
    }

    public function test_renders_reserved_name_collision_for_a_single_method(): void
    {
        $exception = ReservedNameCollisionException::forCollisions('ExpectsCollisionInterface', ['expects']);

        $this->assertMatchesGolden('reserved-name-collision-single', $exception->getMessage());
    }

    public function test_renders_reserved_name_collision_for_multiple_methods(): void
    {
        $exception = ReservedNameCollisionException::forCollisions('Fillable&Sized', ['expects', 'verify']);

        $this->assertMatchesGolden('reserved-name-collision-multiple', $exception->getMessage());
    }
}
