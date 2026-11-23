<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Double;
use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Integrations\PHPUnit\PHPUnitAmbiguousExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallMismatchException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitOutOfOrderCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException;
use JMac\Testing\Matching\Argument;
use JMac\Testing\Matching\Matcher;
use JMac\Testing\Tests\Support\HasDefaultArguments;
use JMac\Testing\Tests\Support\Suit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultArgumentMatchingTest extends TestCase
{
    public function test_expects_matches_an_omitted_trailing_default(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and')->returns(['matched']);

        $this->assertSame(['matched'], $double->where('a', '=', 'b'));

        $double->verify();
    }

    public function test_allows_matches_an_omitted_trailing_default(): void
    {
        $double = Double::for(HasDefaultArguments::class)->strict();
        $double->allows('where')->with('a', '=', 'b', 'and')->returns(['matched']);

        $this->assertSame(['matched'], $double->where('a', '=', 'b'));
    }

    public function test_received_matches_an_omitted_trailing_default(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->where('a', '=', 'b');

        $double->received('where')->with('a', '=', 'b', 'and')->times(1);

        $this->addToAssertionCount(1);
    }

    public function test_received_counts_exact_and_default_calls_together(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->where('a', '=', 'b');
        $double->where('a', '=', 'b', 'and');
        $double->where('a', '=', 'b', 'or');

        $double->received('where')->with('a', '=', 'b', 'and')->times(2);

        $this->addToAssertionCount(1);
    }

    public function test_received_never_fails_for_a_call_matching_through_a_default(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->where('a', '=', 'b');

        $this->expectException(PHPUnitUnsatisfiedReceivedAssertionException::class);

        $double->received('where')->with('a', '=', 'b', 'and')->never();
    }

    public function test_a_different_value_does_not_match_the_default(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'or');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->where('a', '=', 'b');
    }

    public function test_default_comparison_is_strict(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('options')->with('a', '10');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->options('a');
    }

    public function test_supplied_arguments_must_still_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->where('a', '=', 'different');
    }

    public function test_matchers_still_apply_to_supplied_arguments(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with(Argument::type('string'), Argument::any(), 'b', 'and')->returns(['matched']);

        $this->assertSame(['matched'], $double->where('a', '=', 'b'));
    }

    public function test_multiple_defaults_of_each_type_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('options')->with('a', 10, ['a'], Suit::Hearts, 'constant')->returns(99);

        $this->assertSame(99, $double->options('a'));
    }

    public function test_partial_defaults_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('options')->with('a', 10, ['a'])->returns(99);

        $this->assertSame(99, $double->options('a'));
    }

    public function test_exact_match_wins_over_an_earlier_or_later_default_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->allows('other')->with('name')->returns('default');
        $double->allows('other')->with()->returns('exact');
        $double->allows('other')->with('name')->returns('default again');

        $this->assertSame('exact', $double->other());
    }

    public function test_exact_generic_match_wins_over_a_default_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->allows('other')->returns('generic');
        $double->allows('other')->with('name')->returns('default');

        $this->assertSame('generic', $double->other());
    }

    public function test_an_exhausted_exact_match_is_not_bypassed_by_a_default_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('other')->with()->returns('exact');
        $double->allows('other')->with('name')->returns('default');

        $this->assertSame('exact', $double->other());

        $this->expectException(PHPUnitExpectationCallLimitExceededException::class);
        $this->expectExceptionMessage('1 other expectation for `other` also matches');

        $double->other();
    }

    public function test_never_on_an_exact_match_is_not_bypassed_by_a_default_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('other')->with()->never();
        $double->allows('other')->with('name');

        $this->expectException(PHPUnitExpectationCallLimitExceededException::class);

        $double->other();
    }

    #[DataProvider('nullBoundaries')]
    public function test_null_default_never_matches(array $constraints, array $arguments): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with(...$constraints);

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->where(...$arguments);
    }

    public static function nullBoundaries(): array
    {
        return [
            'explicit null for omitted value' => [['a', '=', null], ['a', '=']],
            'null value before non-null default' => [['a', '=', null, 'and'], ['a', '=']],
            'null operator and value' => [['a', null, null, 'and'], ['a']],
        ];
    }

    public function test_received_does_not_match_a_null_default(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->where('a', '=');

        $double->received('where')->with('a', '=', null, 'and')->never();

        $this->addToAssertionCount(1);
    }

    public function test_padding_stops_at_an_object_default(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('objectDefault')->with('a', null, 'after');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->objectDefault('a');
    }

    public function test_padding_stops_at_a_variadic_parameter(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('variadic')->with('a', 'next', 'extra');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->variadic('a');
    }

    public function test_default_before_a_variadic_parameter_matches(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('variadic')->with('a', 'next')->returns(99);

        $this->assertSame(99, $double->variadic('a'));
    }

    public function test_padding_stops_at_a_parameter_without_a_default(): void
    {
        $double = Double::for(HasDefaultArguments::class);

        $this->assertSame([], Double::stateFor($double)->omittedDefaults('variadic', []));
    }

    public function test_omitted_defaults_stop_at_each_boundary(): void
    {
        $state = Double::stateFor(Double::for(HasDefaultArguments::class));

        $this->assertSame(['and'], $state->omittedDefaults('where', ['a', '=', 'b']));
        $this->assertSame([], $state->omittedDefaults('where', ['a', '=']));
        $this->assertSame([10, ['a'], Suit::Hearts, 'constant'], $state->omittedDefaults('options', ['a']));
        $this->assertSame([], $state->omittedDefaults('objectDefault', ['a']));
        $this->assertSame(['next'], $state->omittedDefaults('variadic', ['a']));
        $this->assertSame([], $state->omittedDefaults('variadic', ['a', 'extra' => 'x']));
        $this->assertSame([], $state->omittedDefaults('namedVariadic', ['a', 'extra' => 'x']));
    }

    public function test_named_variadic_calls_are_not_padded(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('variadic')->with('a', 'next', extra: 'x');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->variadic('a', extra: 'x');
    }

    public function test_named_variadic_calls_do_not_reach_later_defaults(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('namedVariadic')->with('a', 'x', 'third');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->namedVariadic('a', extra: 'x');
    }

    public function test_expectation_rejects_named_arguments_even_with_defaults(): void
    {
        $expectation = (new MethodExpectation('namedVariadic', required: false))->with('a', 'x', 'third');

        $this->assertFalse($expectation->matchesWithDefaults(['a', 'extra' => 'x'], ['third']));
    }

    public function test_php_normalizes_named_declared_parameters_to_positional(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and')->returns(['matched']);

        $this->assertSame(['matched'], $double->where('a', operator: '=', value: 'b'));
    }

    public function test_named_constraints_are_not_padded(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('variadic')->with('a', extra: 'next');

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->variadic('a');
    }

    public function test_named_null_constraint_is_not_padded(): void
    {
        $expectation = (new MethodExpectation('variadic', required: false))->with('a', extra: null);

        $this->assertFalse($expectation->matchesWithDefaults(['a'], ['next']));
    }

    public function test_none_sees_only_actual_arguments(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->allows('other')->with(Argument::none())->returns('none');

        $this->assertSame('none', $double->other());
        $this->assertNotSame('none', $double->other('name'));
    }

    public function test_all_sees_only_actual_arguments(): void
    {
        $seen = null;
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with(Argument::all(function (mixed ...$arguments) use (&$seen): bool {
            $seen = $arguments;

            return true;
        }));

        $double->where('a', '=', 'b');

        $this->assertSame(['a', '=', 'b'], $seen);
    }

    public function test_all_does_not_match_through_omitted_defaults(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with(Argument::all(static fn (mixed ...$arguments): bool => count($arguments) === 4));

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->where('a', '=', 'b');
    }

    public function test_remaining_sees_only_actual_arguments(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', Argument::remaining())->returns(['matched']);

        $this->assertSame(['matched'], $double->where('a', '=', 'b'));
    }

    public function test_remaining_does_not_use_defaults_to_reach_its_minimum(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('options')->with('a', 10, Argument::remaining());

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->options('a');
    }

    #[DataProvider('omittedMatchers')]
    public function test_non_literal_matchers_require_a_supplied_argument(Matcher $matcher): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', $matcher);

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->where('a', '=', 'b');
    }

    public static function omittedMatchers(): array
    {
        $captured = null;

        return [
            'any' => [Argument::any()],
            'any of' => [Argument::any('and', 'or')],
            'same' => [Argument::same('and')],
            'type' => [Argument::type('string')],
            'pattern' => [Argument::matches('/and/')],
            'satisfies' => [Argument::satisfies(static fn (mixed $value): bool => true)],
            'not' => [Argument::not('or')],
            'capture' => [Argument::capture($captured)],
        ];
    }

    public function test_capture_on_a_supplied_argument_works_with_an_omitted_default(): void
    {
        $captured = null;
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', Argument::capture($captured), 'and');

        $double->where('a', '=', 'b');

        $this->assertSame('b', $captured);
    }

    public function test_resolves_receives_only_actual_arguments(): void
    {
        $seen = null;
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and')->resolves(function (mixed ...$arguments) use (&$seen): array {
            $seen = $arguments;

            return [];
        });

        $double->where('a', '=', 'b');

        $this->assertSame(['a', '=', 'b'], $seen);
    }

    public function test_call_history_keeps_only_actual_arguments(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and');

        $double->where('a', '=', 'b');

        $this->assertSame([['a', '=', 'b']], Double::stateFor($double)->callsFor('where'));
    }

    public function test_passthru_forwards_only_actual_arguments(): void
    {
        $double = Double::for(HasDefaultArguments::class)->passthru();
        $double->allows('where')->with('other', '=', 'b', 'and')->returns(['stubbed']);

        $this->assertSame([2, 'a', '=', null, 'and'], $double->where('a', '='));
        $this->assertSame([3, 'a', '=', 'b', 'and'], $double->where('a', '=', 'b'));
    }

    public function test_default_match_preserves_reference_arguments(): void
    {
        $total = 5;
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('addTo')->with(5, 1)->resolves(function (int &$value): void {
            $value = 99;
        });

        $double->addTo($total);

        $this->assertSame(99, $total);
        $this->assertSame([[5]], Double::stateFor($double)->callsFor('addTo'));
    }

    public function test_passthru_uses_a_default_matched_expectation(): void
    {
        $double = Double::for(HasDefaultArguments::class)->passthru();
        $double->allows('where')->with('a', '=', 'b', 'and')->returns(['stubbed']);

        $this->assertSame(['stubbed'], $double->where('a', '=', 'b'));
    }

    public function test_mismatch_message_shows_actual_arguments(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'or');

        try {
            $double->where('a', '=', 'b');
            $this->fail('Expected a mismatch.');
        } catch (PHPUnitExpectationCallMismatchException $exception) {
            $this->assertStringContainsString("'a', '=', 'b'", $exception->getMessage());
            $this->assertStringNotContainsString("'a', '=', 'b', 'and'", $exception->getMessage());
        }
    }

    public function test_call_limit_message_shows_actual_arguments(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and');
        $double->where('a', '=', 'b');

        try {
            $double->where('a', '=', 'b');
            $this->fail('Expected the call limit to be exceeded.');
        } catch (PHPUnitExpectationCallLimitExceededException $exception) {
            $this->assertStringContainsString("where('a', '=', 'b')", $exception->getMessage());
            $this->assertStringNotContainsString("'and'", $exception->getMessage());
            $this->assertSame(0, $exception->otherMatchingExpectations);
        }
    }

    public function test_unsatisfied_count_does_not_report_an_arity_mismatch_for_a_default_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and')->times(2);
        $double->where('a', '=', 'b');

        try {
            $double->verify();
            $this->fail('Expected an unsatisfied expectation.');
        } catch (PHPUnitUnsatisfiedExpectationException $exception) {
            $this->assertNull($exception->expectations[0]->argumentMismatch);
            $this->assertSame(1, $exception->expectations[0]->timesCalled);
        }
    }

    public function test_unsatisfied_received_count_does_not_report_an_arity_mismatch_for_a_default_match(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->where('a', '=', 'b');

        try {
            $double->received('where')->with('a', '=', 'b', 'and')->times(2)->check();
            $this->fail('Expected an unsatisfied assertion.');
        } catch (PHPUnitUnsatisfiedReceivedAssertionException $exception) {
            $this->assertNull($exception->argumentMismatch);
        }
    }

    public function test_call_limit_applies_to_default_matches(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and')->times(2);

        $double->where('a', '=', 'b');
        $double->where('a', '=', 'b', 'and');

        $this->expectException(PHPUnitExpectationCallLimitExceededException::class);

        $double->where('a', '=', 'b');
    }

    public function test_default_matches_fall_through_exhausted_candidates_of_any_length(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('options')->with('a', 10, ['a'])->returns(3);
        $double->expects('options')->with('a', 10)->returns(2);

        $this->assertSame(2, $double->options('a'));
        $this->assertSame(3, $double->options('a'));

        $double->verify();
    }

    public function test_other_matching_count_includes_default_matches(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('options')->with('a', 10, ['a']);
        $double->expects('options')->with('a', 10);
        $double->options('a');
        $double->options('a');

        try {
            $double->options('a');
            $this->fail('Expected the call limit to be exceeded.');
        } catch (PHPUnitExpectationCallLimitExceededException $exception) {
            $this->assertSame(1, $exception->otherMatchingExpectations);
        }
    }

    public function test_ordering_applies_to_default_matches(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and')->ordered();
        $double->expects('other')->with('name')->ordered();

        $double->other('name');

        $this->expectException(PHPUnitOutOfOrderCallException::class);

        $double->where('a', '=', 'b');
    }

    public function test_exact_and_default_forms_are_not_ambiguous(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b');
        $double->expects('where')->with('a', '=', 'b', 'and');

        $double->where('a', '=', 'b');
        $double->where('a', '=', 'b', 'and');

        $double->verify();

        $this->addToAssertionCount(1);
    }

    public function test_identical_default_expectations_are_ambiguous(): void
    {
        $double = Double::for(HasDefaultArguments::class);
        $double->expects('where')->with('a', '=', 'b', 'and');
        $double->expects('where')->with('a', '=', 'b', 'and');

        $double->where('a', '=', 'b');
        $double->where('a', '=', 'b');

        $this->expectException(PHPUnitAmbiguousExpectationException::class);

        $double->verify();
    }
}
