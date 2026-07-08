<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use PHPUnit\Framework\TestCase;
use JMac\Testing\Engine\MethodExpectation;

final class MethodExpectationTest extends TestCase
{
    public function testRequiredExpectationDefaultsToExactlyOnce(): void
    {
        $expectation = new MethodExpectation('find', required: true);

        $this->assertFalse($expectation->isSatisfied());

        $expectation->recordMatch();

        $this->assertTrue($expectation->isSatisfied());
        $this->assertFalse($expectation->exceedsMaximum());

        $expectation->recordMatch();

        $this->assertTrue($expectation->exceedsMaximum());
    }

    public function testOptionalExpectationDefaultsToAnyNumberIncludingZero(): void
    {
        $expectation = new MethodExpectation('find', required: false);

        $this->assertTrue($expectation->isSatisfied());
        $this->assertFalse($expectation->exceedsMaximum());

        for ($i = 0; $i < 50; $i++) {
            $expectation->recordMatch();
        }

        $this->assertFalse($expectation->exceedsMaximum());
    }

    public function testWithOmittedMatchesAnyArguments(): void
    {
        $expectation = new MethodExpectation('find', required: false);

        $this->assertTrue($expectation->matchesArguments([1]));
        $this->assertTrue($expectation->matchesArguments([1, 2, 3]));
        $this->assertTrue($expectation->matchesArguments([]));
    }

    public function testWithConstrainsToMatchingArgumentsByValueEquality(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->with(1, 'two');

        $this->assertTrue($expectation->matchesArguments([1, 'two']));
        $this->assertFalse($expectation->matchesArguments([1, 'three']));
        $this->assertFalse($expectation->matchesArguments([1]));
    }

    public function testNeverSetsMaximumToZero(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->never();

        $expectation->recordMatch();

        $this->assertTrue($expectation->exceedsMaximum());
    }

    public function testTimesSetsAnExactRequiredCount(): void
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

    public function testResolveReturnHoldsAtTheLastValueOnceSequentialReturnsAreExhausted(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->returns('a', 'b');

        $expectation->recordMatch();
        $this->assertSame('a', $expectation->resolveReturn([]));

        $expectation->recordMatch();
        $this->assertSame('b', $expectation->resolveReturn([]));

        $expectation->recordMatch();
        $this->assertSame('b', $expectation->resolveReturn([]));
    }

    public function testResolveReturnThrowsTheConfiguredException(): void
    {
        $expectation = (new MethodExpectation('find', required: false))->throws(new \RuntimeException('boom'));

        $expectation->recordMatch();

        $this->expectExceptionMessage('boom');

        $expectation->resolveReturn([]);
    }

    public function testResolveReturnUsingPassesTheActualCallArgumentsToTheResolver(): void
    {
        $expectation = (new MethodExpectation('find', required: false))
            ->returnsUsing(fn (int $id): string => "id-{$id}");

        $expectation->recordMatch();

        $this->assertSame('id-7', $expectation->resolveReturn([7]));
    }

    public function testHasReturnConfiguredIsFalseUntilAnOutcomeIsSet(): void
    {
        $expectation = new MethodExpectation('find', required: false);

        $this->assertFalse($expectation->hasReturnConfigured());

        $expectation->returns('x');

        $this->assertTrue($expectation->hasReturnConfigured());
    }

    public function testDescribeRendersArgumentsAndExpectedCount(): void
    {
        $expectation = (new MethodExpectation('find', required: true))->with(123);

        $this->assertSame('find(123) — expected exactly 1 time(s), called 0 time(s)', $expectation->describe());
    }
}
