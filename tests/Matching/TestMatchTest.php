<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\AnyMatcher;
use JMac\Testing\Matching\Matcher;
use JMac\Testing\Matching\PredicateMatcher;
use JMac\Testing\Matching\TestMatch;
use JMac\Testing\Matching\TypeMatcher;
use JMac\Testing\Tests\Fixtures\Book;
use PHPUnit\Framework\TestCase;

final class TestMatchTest extends TestCase
{
    public function test_any_produces_an_any_matcher(): void
    {
        $this->assertInstanceOf(AnyMatcher::class, TestMatch::any());
    }

    public function test_type_produces_a_type_matcher_for_the_given_class(): void
    {
        $matcher = TestMatch::type(Book::class);

        $this->assertInstanceOf(TypeMatcher::class, $matcher);
        $this->assertTrue($matcher->matches(new Book('Some Title')));
    }

    public function test_that_produces_a_predicate_matcher(): void
    {
        $matcher = TestMatch::that(fn (int $id): bool => $id > 100);

        $this->assertInstanceOf(PredicateMatcher::class, $matcher);
        $this->assertTrue($matcher->matches(101));
        $this->assertFalse($matcher->matches(1));
    }

    public function test_every_produced_matcher_implements_the_matcher_contract(): void
    {
        $this->assertInstanceOf(Matcher::class, TestMatch::any());
        $this->assertInstanceOf(Matcher::class, TestMatch::type(Book::class));
        $this->assertInstanceOf(Matcher::class, TestMatch::that(fn (): bool => true));
    }
}
