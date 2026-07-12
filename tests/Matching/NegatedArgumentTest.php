<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Matching\NegatedArgument;
use JMac\Testing\Matching\NotMatcher;
use JMac\Testing\Tests\Support\Book;
use PHPUnit\Framework\TestCase;

final class NegatedArgumentTest extends TestCase
{
    public function test_not_with_no_argument_returns_a_negated_argument(): void
    {
        $this->assertInstanceOf(NegatedArgument::class, Argument::not());
    }

    public function test_not_with_an_argument_still_negates_the_bare_literal(): void
    {
        $matcher = Argument::not(5);

        $this->assertInstanceOf(NotMatcher::class, $matcher);
        $this->assertTrue($matcher->matches(6));
        $this->assertFalse($matcher->matches(5));
    }

    public function test_not_with_null_is_the_literal_case_not_the_builder(): void
    {
        $matcher = Argument::not(null);

        $this->assertInstanceOf(NotMatcher::class, $matcher);
        $this->assertTrue($matcher->matches('anything'));
        $this->assertFalse($matcher->matches(null));
    }

    public function test_not_rejects_a_matcher_passed_to_the_one_argument_form(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Argument::not()->verb(...)');

        Argument::not(Argument::type('int'));
    }

    public function test_type_negates_a_type_matcher(): void
    {
        $matcher = Argument::not()->type('int');

        $this->assertTrue($matcher->matches('5'));
        $this->assertFalse($matcher->matches(5));
        $this->assertSame('not(type(int))', $matcher->describe());
    }

    public function test_same_negates_a_same_matcher(): void
    {
        $book = new Book('Some Title');
        $matcher = Argument::not()->same($book);

        $this->assertTrue($matcher->matches(new Book('Some Title')));
        $this->assertFalse($matcher->matches($book));
    }

    public function test_satisfies_negates_a_predicate_matcher(): void
    {
        $matcher = Argument::not()->satisfies(fn (int $id): bool => $id > 100);

        $this->assertTrue($matcher->matches(1));
        $this->assertFalse($matcher->matches(101));
    }

    public function test_contains_negates_a_contains_matcher(): void
    {
        $matcher = Argument::not()->contains('draft');

        $this->assertTrue($matcher->matches(['published']));
        $this->assertFalse($matcher->matches(['draft']));
    }

    public function test_matches_negates_a_pattern_matcher(): void
    {
        $matcher = Argument::not()->matches('/^\d+$/');

        $this->assertTrue($matcher->matches('abc'));
        $this->assertFalse($matcher->matches('123'));
    }

    /**
     * not()->any($a, $b) is notAnyOf($a, $b) — "none of these" — with no
     * dedicated verb needed for it.
     */
    public function test_any_negates_an_any_of_matcher(): void
    {
        $matcher = Argument::not()->any('draft', 'published');

        $this->assertTrue($matcher->matches('deleted'));
        $this->assertFalse($matcher->matches('draft'));
        $this->assertFalse($matcher->matches('published'));
    }
}
