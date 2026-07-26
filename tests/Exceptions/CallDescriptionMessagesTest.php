<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Exceptions;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;
use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Exceptions\UnsatisfiedReceivedAssertionException;
use JMac\Testing\Matching\Argument;
use JMac\Testing\Tests\Support\Book;

/**
 * ExceptionMessagesTest hand-writes its UnsatisfiedExpectation::$description
 * strings, since its focus is the exception-level prose wrapped around a
 * description, not the description itself. This file is the complement:
 * every matcher's describe() and every MethodExpectation::describeExpectedCount()
 * branch, driven through the real MethodExpectation (the same object
 * ProxyBehavior/ReceivedAssertion use), then wrapped in the exception a
 * user actually sees — so the full, realistic message is what's reviewed,
 * not just the matcher/count-phrase fragment in isolation.
 */
final class CallDescriptionMessagesTest extends GoldenFileTestCase
{
    private function unmetExpectation(string $method, MethodExpectation $expectation): string
    {
        $unsatisfied = new UnsatisfiedExpectation(
            method: $method,
            description: $expectation->describe(),
            expectedMin: $expectation->minimumCalls(),
            expectedMax: $expectation->maximumCalls(),
            timesCalled: $expectation->timesMatched(),
            otherObservedCalls: [],
        );

        return (new UnsatisfiedExpectationException('BookRepository', [$unsatisfied]))->getMessage();
    }

    public function test_renders_type_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::type('int'));

        $this->assertMatchesGolden('describe-matcher-type', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_pattern_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::matches('/^\d+$/'));

        $this->assertMatchesGolden('describe-matcher-pattern', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_contains_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::contains('draft'));

        $this->assertMatchesGolden('describe-matcher-contains', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_not_literal_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::not(5));

        $this->assertMatchesGolden('describe-matcher-not-literal', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_not_verb_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::not()->type('int'));

        $this->assertMatchesGolden('describe-matcher-not-verb', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_satisfies_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::satisfies(static fn (mixed $id): bool => is_int($id) && $id > 0));

        $this->assertMatchesGolden('describe-matcher-satisfies', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_same_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::same(new Book('Some Title')));

        $this->assertMatchesGolden('describe-matcher-same', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_any_unconstrained_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::any(), 5);

        $this->assertMatchesGolden('describe-matcher-any-unconstrained', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_any_of_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::any('draft', 'published'));

        $this->assertMatchesGolden('describe-matcher-any-of', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_none_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(Argument::none());

        $this->assertMatchesGolden('describe-matcher-none', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_remaining_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(1, Argument::remaining());

        $this->assertMatchesGolden('describe-matcher-remaining', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_capture_matcher(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $captured = null;
        $expectation->with(Argument::capture($captured));

        $this->assertMatchesGolden('describe-matcher-capture', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_mixed_literal_arguments(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->with(1, 'two', true, null, 3.14);

        $this->assertMatchesGolden('describe-matcher-mixed-literals', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_between_count_bound(): void
    {
        $expectation = new MethodExpectation('delete', required: false);
        $expectation->times(2, 4);

        $this->assertMatchesGolden('describe-count-between', $this->unmetExpectation('delete', $expectation));
    }

    public function test_renders_exactly_count_bound_pluralized(): void
    {
        $expectation = new MethodExpectation('save', required: true);
        $expectation->times(3);
        $expectation->recordMatch();

        $this->assertMatchesGolden('describe-count-exactly-plural', $this->unmetExpectation('save', $expectation));
    }

    public function test_renders_at_least_count_bound(): void
    {
        $expectation = new MethodExpectation('delete', required: false);
        $expectation->atLeastOnce();

        $this->assertMatchesGolden('describe-count-at-least', $this->unmetExpectation('delete', $expectation));
    }

    /**
     * describeExpectedCount()'s "at most" branch (minimumCalls === 0, a
     * finite maximumCalls) can never be unmet at verify() time — isSatisfied()
     * is trivially true whenever the minimum is 0, so unmetExpectations()
     * never selects it (see DoubleState::unmetExpectations()). It's only
     * ever reachable via a received() assertion catching too many calls
     * after the fact — the same "expected X, called Y" describe() text,
     * just fed through UnsatisfiedReceivedAssertionException instead,
     * exactly as ReceivedAssertion::check() does.
     */
    public function test_renders_at_most_count_bound(): void
    {
        $expectation = new MethodExpectation('find', required: false);
        $expectation->times(maximum: 2);
        $expectation->recordMatch();
        $expectation->recordMatch();
        $expectation->recordMatch();

        $exception = new UnsatisfiedReceivedAssertionException('BookRepository', $expectation->describe());

        $this->assertMatchesGolden('describe-count-at-most', $exception->getMessage());
    }
}
