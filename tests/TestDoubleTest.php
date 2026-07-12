<?php

declare(strict_types=1);

namespace JMac\Testing\Tests;

use JMac\Testing\Engine\ReceivedAssertion;
use JMac\Testing\Exceptions\ModeConfigurationException;
use JMac\Testing\Exceptions\StaticMethodException;
use JMac\Testing\Exceptions\UnknownMethodException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitOutOfOrderCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException;
use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use JMac\Testing\Tests\Support\Book;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use JMac\Testing\Tests\Support\Fillable;
use JMac\Testing\Tests\Support\HasStaticMethod;
use JMac\Testing\Tests\Support\Sized;
use JMac\Testing\Tests\Support\VariadicInterface;
use PHPUnit\Framework\TestCase;

final class TestDoubleTest extends TestCase
{
    /**
     * Deliberately a property, not a local variable, for the
     * $pendingReceived tests below: PHP tears down a method's own locals
     * the instant that method returns — before verifyAll() could ever be
     * invoked from a separate #[After] hook the way VerifiesDoubles really
     * uses it — so a local variable can't actually reproduce the gap
     * $pendingReceived closes. A property on $this survives past the test
     * method's own return (this TestCase instance isn't destroyed until
     * well after its own #[After] hooks would run), which is what makes
     * these tests a real reproduction instead of a false positive.
     */
    private ?ReceivedAssertion $heldAssertion = null;

    public function test_for_returns_an_instance_of_the_target(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->assertInstanceOf(BookRepositoryInterface::class, $double);
    }

    public function test_for_with_multiple_targets_returns_a_double_satisfying_all_of_them(): void
    {
        $double = TestDouble::for(Fillable::class, Sized::class);

        $this->assertInstanceOf(Fillable::class, $double);
        $this->assertInstanceOf(Sized::class, $double);
    }

    public function test_for_with_multiple_targets_configures_methods_declared_on_either_one(): void
    {
        $double = TestDouble::for(Fillable::class, Sized::class);

        $double->allows('fill')->returns(true);
        $double->allows('size')->returns(3);

        $this->assertTrue($double->fill());
        $this->assertSame(3, $double->size());
    }

    public function test_for_with_multiple_targets_uses_a_combined_short_label_in_messages(): void
    {
        $double = TestDouble::for(Fillable::class, Sized::class);
        $double->expects('fill')->returns(true);

        // Regression check: label derivation used to take the short name of
        // the whole "&"-joined string in one pass, which silently dropped
        // every candidate but the last (see TestDouble::deriveLabel()) —
        // this double's label would have rendered as just "Sized".
        $this->expectException(PHPUnitUnsatisfiedExpectationException::class);
        $this->expectExceptionMessage('Fillable&Sized');

        $double->verify();
    }

    public function test_for_with_no_targets_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TestDouble::for();
    }

    public function test_allows_configures_a_return_value_for_a_matching_call(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $book = new Book('Dune');

        $double->allows('find')->with(1)->returns($book);

        $this->assertSame($book, $double->find(1));
    }

    public function test_allows_may_be_called_any_number_of_times_including_zero(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('save')->returns(true);

        $double->verify();

        $this->assertTrue($double->save(new Book('Dune')));
        $this->assertTrue($double->save(new Book('Dune Messiah')));

        $double->verify();
    }

    public function test_expects_defaults_to_exactly_once(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $double->delete(1);

        $double->verify();
        $this->addToAssertionCount(1);
    }

    public function test_expects_fails_verify_when_never_called(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $this->expectException(PHPUnitUnsatisfiedExpectationException::class);
        $this->expectExceptionMessageMatches('/delete\(any arguments\).*exactly 1 time, called 0 times/s');

        $double->verify();
    }

    public function test_expects_throws_when_called_more_times_than_allowed(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $double->delete(1);

        $this->expectException(PHPUnitExpectationCallLimitExceededException::class);

        $double->delete(1);
    }

    public function test_last_registered_matching_expectation_wins(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $default = new Book('Default');
        $specific = new Book('Specific');

        $double->allows('find')->returns($default);
        $double->allows('find')->with(123)->returns($specific);

        $this->assertSame($specific, $double->find(123));
        $this->assertSame($default, $double->find(456));
    }

    public function test_in_order_calls_made_in_declared_order_succeed(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('find')->inOrder();
        $double->allows('save')->inOrder();
        $double->allows('delete')->inOrder();

        $double->find(1);
        $double->save(new Book('Dune'));
        $double->delete(1);

        $this->addToAssertionCount(1);
    }

    public function test_in_order_calls_out_of_declared_order_throw_immediately(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('find')->inOrder();
        $double->allows('save')->inOrder();

        $double->save(new Book('Dune'));

        $this->expectException(PHPUnitOutOfOrderCallException::class);
        $this->expectExceptionMessage('received "find()" out of order: "save()" already happened');

        // find() is earlier in the declared sequence than save(), which
        // already happened — calling it now is a regression.
        $double->find(1);
    }

    public function test_in_order_ignores_calls_to_expectations_not_themselves_marked_in_order(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('find')->inOrder();
        $double->allows('delete')->inOrder();
        $double->allows('save')->returns(true); // not ordered

        $double->find(1);
        $double->save(new Book('Dune')); // unordered — freely interleaved
        $double->delete(1);

        $this->addToAssertionCount(1);
    }

    public function test_in_order_allows_skipping_ahead_without_every_step_occurring(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('find')->inOrder();
        $double->allows('save')->inOrder();
        $double->allows('delete')->inOrder();

        // save() (the middle step) never happens — jumping straight from
        // find() to delete() is a forward skip, not a regression, and is
        // allowed (mirrors Mockery's own validateOrder() — see
        // ARCHITECTURE.md, "Call-order enforcement"). A skipped required
        // step still surfaces separately, via the ordinary
        // unmet-expectation check at verify() time.
        $double->find(1);
        $double->delete(1);

        $this->addToAssertionCount(1);
    }

    public function test_in_order_is_scoped_per_double_not_across_doubles(): void
    {
        $first = TestDouble::for(BookRepositoryInterface::class);
        $second = TestDouble::for(BookRepositoryInterface::class);

        $first->allows('find')->inOrder();
        $first->allows('save')->inOrder();
        $second->allows('delete')->inOrder();
        $second->allows('count')->inOrder();

        // Interleaved across two doubles — each double's own declared
        // sequence is independent, so this is a violation on neither.
        $second->delete(1);
        $first->find(1);
        $second->count();
        $first->save(new Book('Dune'));

        $this->addToAssertionCount(1);
    }

    public function test_in_order_works_with_expects_as_well_as_allows(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('find')->returns(null)->inOrder();
        $double->expects('save')->returns(true)->inOrder();

        $double->find(1);
        $double->save(new Book('Dune'));

        $double->verify();
        $this->addToAssertionCount(1);
    }

    public function test_sequential_returns_hold_at_the_last_value_on_further_calls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $first = new Book('First');
        $second = new Book('Second');

        $double->allows('find')->returns($first, $second);

        $this->assertSame($first, $double->find(1));
        $this->assertSame($second, $double->find(1));
        $this->assertSame($second, $double->find(1));
    }

    public function test_throws_configures_an_exception_to_be_thrown(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $exception = new \OutOfBoundsException('not found');

        $double->allows('find')->with(999)->throws($exception);

        $this->expectExceptionObject($exception);

        $double->find(999);
    }

    public function test_sequential_throws_hold_at_the_last_exception_on_further_calls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $first = new \OutOfBoundsException('first call fails');
        $second = new \RuntimeException('second call fails');

        $double->allows('find')->throws($first, $second);

        try {
            $double->find(1);
            $this->fail('Expected the first exception to be thrown.');
        } catch (\OutOfBoundsException $exception) {
            $this->assertSame($first, $exception);
        }

        try {
            $double->find(1);
            $this->fail('Expected the second exception to be thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($second, $exception);
        }

        try {
            $double->find(1);
            $this->fail('Expected the second exception to be thrown again.');
        } catch (\RuntimeException $exception) {
            $this->assertSame($second, $exception);
        }
    }

    public function test_resolves_computes_the_value_from_the_actual_arguments(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->allows('find')->resolves(fn (int $id): Book => new Book("Book #{$id}"));

        $this->assertSame('Book #42', $double->find(42)->title);
    }

    public function test_capture_writes_the_actual_argument_into_the_referenced_variable(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $captured = null;
        $dune = new Book('Dune');

        $double->allows('save')->with(Argument::capture($captured))->returns(true);

        $double->save($dune);

        $this->assertSame($dune, $captured);
    }

    public function test_capture_does_not_write_when_its_own_expectation_ends_up_not_matching(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $captured = 'sentinel';

        // Older — checked *after* the expectation below. The capture sits at
        // position 0 (would match anything), but position 1 requires a Book,
        // so this expectation still fails to match overall.
        $double->allows('find')->with(Argument::capture($captured), Argument::type(Book::class))->returns(null);
        // Newer — checked first, and fails on position 0 before ever reaching
        // position 1, so the loop falls through to the expectation above.
        $double->allows('find')->with(999, 'irrelevant')->returns(null);

        $this->assertNull($double->find(1, 'not a book')); // matches neither -> loose default

        $this->assertSame('sentinel', $captured); // unchanged - the capturing expectation never actually matched
    }

    public function test_type_matches_a_builtin_php_type_by_name_not_just_a_class(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $book = new Book('Dune');

        $double->allows('find')->with(Argument::type('int'))->returns($book);

        $this->assertSame($book, $double->find(42));
    }

    public function test_with_remaining_constrains_only_the_leading_arguments_end_to_end(): void
    {
        $double = TestDouble::for(VariadicInterface::class);

        $double->allows('combine')->with('-', Argument::remaining())->returns('stubbed');

        $this->assertSame('stubbed', $double->combine('-', 'a'));
        $this->assertSame('stubbed', $double->combine('-', 'a', 'b', 'c'));
    }

    public function test_received_with_remaining_composes_the_same_way_as_expects(): void
    {
        $double = TestDouble::for(VariadicInterface::class);

        $double->combine('-', 'a', 'b', 'c');

        $double->received('combine')->with('-', Argument::remaining());

        $this->addToAssertionCount(1);
    }

    public function test_never_forbids_any_call_at_all(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('delete')->never();

        $this->expectException(PHPUnitExpectationCallLimitExceededException::class);

        $double->delete(1);
    }

    public function test_at_least_once_is_satisfied_by_multiple_calls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null)->atLeastOnce();

        $double->delete(1);
        $double->delete(2);
        $double->delete(3);

        $double->verify();
        $this->addToAssertionCount(1);
    }

    public function test_times_requires_exactly_that_many_calls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null)->times(2);

        $double->delete(1);
        $double->delete(2);

        $double->verify();
        $this->addToAssertionCount(1);
    }

    public function test_times_with_a_range_requires_a_call_count_within_bounds(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null)->times(1, 3);

        $double->delete(1);
        $double->delete(2);

        $double->verify();
        $this->addToAssertionCount(1);
    }

    public function test_times_with_a_named_maximum_fails_once_exceeded(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('delete')->returns(null)->times(maximum: 2);

        $double->delete(1);
        $double->delete(2);

        $this->expectException(PHPUnitExpectationCallLimitExceededException::class);

        $double->delete(3);
    }

    public function test_strict_mode_throws_immediately_on_an_unmatched_call(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class)->strict();

        $this->expectException(PHPUnitUnexpectedCallException::class);

        $double->count();
    }

    public function test_mode_can_only_be_set_once(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class)->strict();

        $this->expectException(ModeConfigurationException::class);

        $double->strict();
    }

    public function test_expects_rejects_an_undeclared_method_name(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(UnknownMethodException::class);
        $this->expectExceptionMessage('bogus');

        $double->expects('bogus');
    }

    public function test_expects_suggests_the_closest_declared_method_name_for_a_likely_typo(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(UnknownMethodException::class);
        $this->expectExceptionMessage('Did you mean "save"?');

        $double->expects('sav');
    }

    /**
     * "make" genuinely exists on HasStaticMethod, so this must fail with
     * StaticMethodException specifically, not UnknownMethodException — a
     * static method is a different problem (unconfigurable) from a typo
     * (nonexistent).
     */
    public function test_expects_rejects_a_static_method(): void
    {
        $double = TestDouble::for(HasStaticMethod::class);

        $this->expectException(StaticMethodException::class);
        $this->expectExceptionMessage('static method');

        $double->expects('make');
    }

    public function test_received_passes_when_the_method_was_called_at_least_once(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->delete(1);
        $double->received('delete');

        $this->addToAssertionCount(1);
    }

    public function test_received_fails_when_the_method_was_never_called(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(PHPUnitUnsatisfiedReceivedAssertionException::class);
        $this->expectExceptionMessageMatches('/delete\(any arguments\).*expected at least 1 time, called 0 times/s');

        $double->received('delete');
    }

    public function test_received_with_passes_when_a_matching_call_was_observed(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $dune = new Book('Dune');

        $double->save($dune);
        $double->received('save')->with($dune);

        $this->addToAssertionCount(1);
    }

    public function test_received_with_fails_when_only_a_non_matching_call_was_observed(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->save(new Book('Dune Messiah'));

        $this->expectException(PHPUnitUnsatisfiedReceivedAssertionException::class);

        $double->received('save')->with(new Book('Dune'));
    }

    public function test_received_never_passes_when_the_method_was_not_called(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->received('delete')->never();

        $this->addToAssertionCount(1);
    }

    public function test_received_never_fails_when_the_method_was_called(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->delete(1);

        $this->expectException(PHPUnitUnsatisfiedReceivedAssertionException::class);
        $this->expectExceptionMessageMatches('/expected exactly 0 times, called 1 time/');

        $double->received('delete')->never();
    }

    public function test_received_times_requires_the_exact_count(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->delete(1);
        $double->delete(2);

        $this->expectException(PHPUnitUnsatisfiedReceivedAssertionException::class);
        $this->expectExceptionMessageMatches('/expected exactly 3 times, called 2 times/');

        $double->received('delete')->times(3);
    }

    public function test_received_composes_with_and_never_to_assert_specific_arguments_were_not_received(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $protected = new Book('Protected');

        $double->save(new Book('Fine to delete'));

        // Composing with()+never() only works because the check happens once,
        // at chain destruction, not eagerly on with() itself — see
        // ReceivedAssertion's docblock.
        $double->received('save')->with($protected)->never();

        $this->addToAssertionCount(1);
    }

    public function test_received_rejects_an_undeclared_method_name(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(UnknownMethodException::class);
        $this->expectExceptionMessage('bogus');

        $double->received('bogus');
    }

    public function test_received_suggests_the_closest_declared_method_name_for_a_likely_typo(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(UnknownMethodException::class);
        $this->expectExceptionMessage('Did you mean "save"?');

        $double->received('sav');
    }

    public function test_received_rejects_a_static_method(): void
    {
        $double = TestDouble::for(HasStaticMethod::class);

        $this->expectException(StaticMethodException::class);
        $this->expectExceptionMessage('static method');

        $double->received('make');
    }

    public function test_verify_passes_when_no_expectations_were_configured_at_all(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->verify();

        $this->addToAssertionCount(1);
    }

    public function test_verify_failure_correlates_other_calls_observed_for_the_same_method(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('find')->returns(null);
        $double->expects('find')->with(123)->returns(new Book('Dune'));

        $double->find(456);

        try {
            $double->verify();
            $this->fail('Expected UnsatisfiedExpectationException to be thrown.');
        } catch (PHPUnitUnsatisfiedExpectationException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('find(123) — expected exactly 1 time, called 0 times', $message);
            $this->assertStringContainsString('"find" was called with different arguments elsewhere in this test:', $message);
            $this->assertStringContainsString('find(456)', $message);
        }
    }

    public function test_verify_all_passes_when_every_double_created_since_arming_is_satisfied(): void
    {
        TestDouble::armAutoVerify();

        $first = TestDouble::for(BookRepositoryInterface::class);
        $second = TestDouble::for(BookRepositoryInterface::class);
        $first->expects('delete')->returns(null);
        $second->allows('save')->returns(true);

        $first->delete(1);

        TestDouble::verifyAll();

        $this->addToAssertionCount(1);
    }

    public function test_verify_all_fails_when_any_double_created_since_arming_has_an_unmet_expectation(): void
    {
        TestDouble::armAutoVerify();

        $satisfied = TestDouble::for(BookRepositoryInterface::class);
        $unsatisfied = TestDouble::for(BookRepositoryInterface::class);
        $satisfied->expects('delete')->returns(null);
        $unsatisfied->expects('save')->returns(true);

        $satisfied->delete(1);

        $this->expectException(PHPUnitUnsatisfiedExpectationException::class);
        $this->expectExceptionMessageMatches('/save\(any arguments\).*expected exactly 1 time, called 0 times/s');

        TestDouble::verifyAll();
    }

    public function test_verify_all_only_covers_doubles_created_after_arming(): void
    {
        // Created before arming — verifyAll() must never see this one, even
        // though it has a real unmet expectation.
        $before = TestDouble::for(BookRepositoryInterface::class);
        $before->expects('delete')->returns(null);

        TestDouble::armAutoVerify();

        $after = TestDouble::for(BookRepositoryInterface::class);
        $after->expects('save')->returns(true);
        $after->save(new Book('Dune'));

        TestDouble::verifyAll();

        $this->addToAssertionCount(1);
    }

    public function test_verify_all_drains_pending_doubles_so_a_second_call_is_a_no_op(): void
    {
        TestDouble::armAutoVerify();

        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);
        $double->delete(1);

        TestDouble::verifyAll();

        // $double already left the pending list on the call above, so this
        // has nothing left to check regardless of $double's own state.
        TestDouble::verifyAll();

        $this->addToAssertionCount(1);
    }

    /**
     * The scenario ReceivedAssertion's docblock describes: a received()
     * chain stored somewhere that outlives the statement that created it
     * (here, $this->heldAssertion — see that property's own docblock for
     * why it has to be a property, not a local, to actually prove this),
     * so it can't have reached its own __destruct() by the time verifyAll()
     * runs. Before $pendingReceived existed, verifyAll() had no way to know
     * this assertion existed at all and would pass regardless of whether
     * "save" was ever actually called.
     */
    public function test_verify_all_checks_a_received_assertion_held_past_the_test_method(): void
    {
        TestDouble::armAutoVerify();

        $double = TestDouble::for(BookRepositoryInterface::class);
        $this->heldAssertion = $double->received('save');

        $this->expectException(PHPUnitUnsatisfiedReceivedAssertionException::class);

        TestDouble::verifyAll();
    }

    public function test_verify_all_passes_a_satisfied_received_assertion_held_past_the_test_method(): void
    {
        TestDouble::armAutoVerify();

        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->save(new Book('Dune'));
        $this->heldAssertion = $double->received('save');

        TestDouble::verifyAll();

        $this->addToAssertionCount(1);
    }

    public function test_verify_all_drains_pending_received_assertions_so_a_second_call_is_a_no_op(): void
    {
        TestDouble::armAutoVerify();

        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->save(new Book('Dune'));
        $this->heldAssertion = $double->received('save');

        TestDouble::verifyAll();

        // $this->heldAssertion already left the pending list and was
        // checked on the call above, so this has nothing left to check
        // regardless of its own state.
        TestDouble::verifyAll();

        $this->addToAssertionCount(1);
    }
}
