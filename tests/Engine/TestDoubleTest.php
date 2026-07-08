<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use PHPUnit\Framework\TestCase;
use JMac\Testing\Engine\TestDouble;
use JMac\Testing\Exceptions\ExpectationCallLimitExceededException;
use JMac\Testing\Exceptions\ModeConfigurationException;
use JMac\Testing\Exceptions\UnconfiguredReturnException;
use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnknownMethodException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Tests\Fixtures\Book;
use JMac\Testing\Tests\Fixtures\BookRepositoryInterface;

final class TestDoubleTest extends TestCase
{
    public function testForReturnsAnInstanceOfTheTarget(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->assertInstanceOf(BookRepositoryInterface::class, $double);
    }

    public function testAllowsConfiguresAReturnValueForAMatchingCall(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $book = new Book('Dune');

        $double->allows('find')->with(1)->returns($book);

        $this->assertSame($book, $double->find(1));
    }

    public function testAllowsMayBeCalledAnyNumberOfTimesIncludingZero(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('save')->returns(true);

        TestDouble::verify($double);

        $this->assertTrue($double->save(new Book('Dune')));
        $this->assertTrue($double->save(new Book('Dune Messiah')));

        TestDouble::verify($double);
    }

    public function testExpectsDefaultsToExactlyOnce(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $double->delete(1);

        TestDouble::verify($double);
        $this->addToAssertionCount(1);
    }

    public function testExpectsFailsVerifyWhenNeverCalled(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $this->expectException(UnsatisfiedExpectationException::class);
        $this->expectExceptionMessageMatches('/delete\(any arguments\).*exactly 1 time\(s\), called 0 time\(s\)/s');

        TestDouble::verify($double);
    }

    public function testExpectsThrowsWhenCalledMoreTimesThanAllowed(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null);

        $double->delete(1);

        $this->expectException(ExpectationCallLimitExceededException::class);

        $double->delete(1);
    }

    public function testLastRegisteredMatchingExpectationWins(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $default = new Book('Default');
        $specific = new Book('Specific');

        $double->allows('find')->returns($default);
        $double->allows('find')->with(123)->returns($specific);

        $this->assertSame($specific, $double->find(123));
        $this->assertSame($default, $double->find(456));
    }

    public function testSequentialReturnsHoldAtTheLastValueOnFurtherCalls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $first = new Book('First');
        $second = new Book('Second');

        $double->allows('find')->returns($first, $second);

        $this->assertSame($first, $double->find(1));
        $this->assertSame($second, $double->find(1));
        $this->assertSame($second, $double->find(1));
    }

    public function testThrowsConfiguresAnExceptionToBeThrown(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $exception = new \OutOfBoundsException('not found');

        $double->allows('find')->with(999)->throws($exception);

        $this->expectExceptionObject($exception);

        $double->find(999);
    }

    public function testReturnsUsingComputesTheValueFromTheActualArguments(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $double->allows('find')->returnsUsing(fn (int $id): Book => new Book("Book #{$id}"));

        $this->assertSame('Book #42', $double->find(42)->title);
    }

    public function testNeverForbidsAnyCallAtAll(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('delete')->never();

        $this->expectException(ExpectationCallLimitExceededException::class);

        $double->delete(1);
    }

    public function testAtLeastOnceIsSatisfiedByMultipleCalls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null)->atLeastOnce();

        $double->delete(1);
        $double->delete(2);
        $double->delete(3);

        TestDouble::verify($double);
        $this->addToAssertionCount(1);
    }

    public function testTimesRequiresExactlyThatManyCalls(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->expects('delete')->returns(null)->times(2);

        $double->delete(1);
        $double->delete(2);

        TestDouble::verify($double);
        $this->addToAssertionCount(1);
    }

    public function testStrictModeThrowsImmediatelyOnAnUnmatchedCall(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class)->strict();

        $this->expectException(UnexpectedCallException::class);

        $double->count();
    }

    public function testAnUnconfiguredDoubleBehavesAsStrictInM1(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(UnexpectedCallException::class);

        $double->count();
    }

    public function testModeCanOnlyBeSetOnce(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class)->strict();

        $this->expectException(ModeConfigurationException::class);

        $double->strict();
    }

    public function testExpectsRejectsAnUndeclaredMethodName(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(UnknownMethodException::class);
        $this->expectExceptionMessage('bogus');

        $double->expects('bogus');
    }

    public function testAMatchedCallWithNoConfiguredReturnFailsLoudlyInsteadOfSilentlyReturningNull(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('count');

        $this->expectException(UnconfiguredReturnException::class);

        $double->count();
    }

    public function testPassthruIsNotImplementedYet(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('M4');

        $double->passthru();
    }

    public function testReceivedIsNotImplementedYet(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(\LogicException::class);

        $double->received('find');
    }

    public function testVerifyPassesWhenNoExpectationsWereConfiguredAtAll(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        TestDouble::verify($double);

        $this->addToAssertionCount(1);
    }
}
