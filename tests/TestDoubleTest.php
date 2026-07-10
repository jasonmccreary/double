<?php

declare(strict_types=1);

namespace JMac\Testing\Tests;

use JMac\Testing\Exceptions\ModeConfigurationException;
use JMac\Testing\Exceptions\UnknownMethodException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnexpectedCallException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use JMac\Testing\Matching\Argument;
use JMac\Testing\TestDouble;
use JMac\Testing\Tests\Fixtures\Book;
use JMac\Testing\Tests\Fixtures\BookRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class TestDoubleTest extends TestCase
{
    public function test_for_returns_an_instance_of_the_target(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->assertInstanceOf(BookRepositoryInterface::class, $double);
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

        TestDouble::verify($double);

        $this->assertTrue($double->save(new Book('Dune')));
        $this->assertTrue($double->save(new Book('Dune Messiah')));

        TestDouble::verify($double);
    }

    public function test_expects_defaults_to_exactly_once(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $double->delete(1);

        TestDouble::verify($double);
        $this->addToAssertionCount(1);
    }

    public function test_expects_fails_verify_when_never_called(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $this->expectException(PHPUnitUnsatisfiedExpectationException::class);
        $this->expectExceptionMessageMatches('/delete\(any arguments\).*exactly 1 time\(s\), called 0 time\(s\)/s');

        TestDouble::verify($double);
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

        TestDouble::verify($double);
        $this->addToAssertionCount(1);
    }

    public function test_times_requires_exactly_that_many_calls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null)->times(2);

        $double->delete(1);
        $double->delete(2);

        TestDouble::verify($double);
        $this->addToAssertionCount(1);
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

    public function test_received_is_not_implemented_yet(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(\LogicException::class);

        $double->received('find');
    }

    public function test_verify_passes_when_no_expectations_were_configured_at_all(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        TestDouble::verify($double);

        $this->addToAssertionCount(1);
    }

    public function test_verify_failure_correlates_other_calls_observed_for_the_same_method(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('find')->returns(null);
        $double->expects('find')->with(123)->returns(new Book('Dune'));

        $double->find(456);

        try {
            TestDouble::verify($double);
            $this->fail('Expected UnsatisfiedExpectationException to be thrown.');
        } catch (PHPUnitUnsatisfiedExpectationException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('find(123) — expected exactly 1 time(s), called 0 time(s)', $message);
            $this->assertStringContainsString('"find" was called with different arguments elsewhere in this test:', $message);
            $this->assertStringContainsString('find(456)', $message);
        }
    }
}
