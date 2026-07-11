<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Matching\Argument;
use JMac\Testing\Tests\Fixtures\Book;
use PHPUnit\Framework\TestCase;

final class MethodExpectationTest extends TestCase
{
    public function test_required_expectation_defaults_to_exactly_once(): void
    {
        $expectation = new MethodExpectation('find', required: true);

        $this->assertFalse($expectation->isSatisfied());

        $expectation->recordMatch();

        $this->assertTrue($expectation->isSatisfied());
        $this->assertFalse($expectation->exceedsMaximum());

        $expectation->recordMatch();

        $this->assertTrue($expectation->exceedsMaximum());
    }

    public function test_optional_expectation_defaults_to_any_number_including_zero(): void
    {
        $expectation = new MethodExpectation('find', required: false);

        $this->assertTrue($expectation->isSatisfied());
        $this->assertFalse($expectation->exceedsMaximum());

        for ($i = 0; $i < 50; $i++) {
            $expectation->recordMatch();
        }

        $this->assertFalse($expectation->exceedsMaximum());
    }

    public function test_with_omitted_matches_any_arguments(): void
    {
        $expectation = new MethodExpectation('find', required: false);

        $this->assertTrue($expectation->matchesArguments([1]));
        $this->assertTrue($expectation->matchesArguments([1, 2, 3]));
        $this->assertTrue($expectation->matchesArguments([]));
    }

    public function test_with_constrains_to_matching_arguments_by_value_equality(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->with(1, 'two');

        $this->assertTrue($expectation->matchesArguments([1, 'two']));
        $this->assertFalse($expectation->matchesArguments([1, 'three']));
        $this->assertFalse($expectation->matchesArguments([1]));
    }

    public function test_never_sets_maximum_to_zero(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->never();

        $expectation->recordMatch();

        $this->assertTrue($expectation->exceedsMaximum());
    }

    public function test_times_sets_an_exact_required_count(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->times(2);

        $expectation->recordMatch();
        $this->assertFalse($expectation->isSatisfied());

        $expectation->recordMatch();
        $this->assertTrue($expectation->isSatisfied());
        $this->assertFalse($expectation->exceedsMaximum());

        $expectation->recordMatch();
        $this->assertTrue($expectation->exceedsMaximum());
    }

    public function test_resolve_return_holds_at_the_last_value_once_sequential_returns_are_exhausted(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->returns('a', 'b');

        $expectation->recordMatch();
        $this->assertSame('a', $expectation->resolveReturn([]));

        $expectation->recordMatch();
        $this->assertSame('b', $expectation->resolveReturn([]));

        $expectation->recordMatch();
        $this->assertSame('b', $expectation->resolveReturn([]));
    }

    public function test_resolve_return_throws_the_configured_exception(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->throws(new \RuntimeException('boom'));

        $expectation->recordMatch();

        $this->expectExceptionMessage('boom');

        $expectation->resolveReturn([]);
    }

    public function test_resolve_return_using_passes_the_actual_call_arguments_to_the_resolver(): void
    {
        $expectation = (new MethodExpectation('find', required: false))
            ->resolves(fn (int $id): string => "id-{$id}");

        $expectation->recordMatch();

        $this->assertSame('id-7', $expectation->resolveReturn([7]));
    }

    public function test_has_return_configured_is_false_until_an_outcome_is_set(): void
    {
        $expectation = new MethodExpectation('find', required: false);

        $this->assertFalse($expectation->hasReturnConfigured());

        $expectation->returns('x');

        $this->assertTrue($expectation->hasReturnConfigured());
    }

    public function test_describe_renders_arguments_and_expected_count(): void
    {
        $expectation = (new MethodExpectation('find', required: true))->with(123);

        $this->assertSame('find(123) — expected exactly 1 time, called 0 times', $expectation->describe());
    }

    public function test_with_accepts_a_matcher_alongside_bare_literals(): void
    {
        $expectation = (new MethodExpectation('find', required: false))
            ->with(Argument::any(), 'two');

        $this->assertTrue($expectation->matchesArguments([1, 'two']));
        $this->assertTrue($expectation->matchesArguments(['anything', 'two']));
        $this->assertFalse($expectation->matchesArguments([1, 'three']));
    }

    public function test_with_type_matcher_constrains_to_instances_of_the_given_class(): void
    {
        $expectation = (new MethodExpectation('save', required: false))
            ->with(Argument::type(Book::class));

        $this->assertTrue($expectation->matchesArguments([new Book('Some Title')]));
        $this->assertFalse($expectation->matchesArguments(['not a book']));
    }

    public function test_with_predicate_matcher_constrains_by_a_callable(): void
    {
        $expectation = (new MethodExpectation('find', required: false))
            ->with(Argument::satisfies(fn (int $id): bool => $id > 100));

        $this->assertTrue($expectation->matchesArguments([101]));
        $this->assertFalse($expectation->matchesArguments([100]));
    }

    public function test_describe_renders_matcher_descriptions(): void
    {
        $expectation = (new MethodExpectation('find', required: true))
            ->with(Argument::any());

        $this->assertSame('find(any()) — expected exactly 1 time, called 0 times', $expectation->describe());
    }
}
