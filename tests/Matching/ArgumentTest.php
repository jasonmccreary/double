<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\AnyMatcher;
use JMac\Testing\Matching\Argument;
use JMac\Testing\Matching\Matcher;
use JMac\Testing\Matching\PredicateMatcher;
use JMac\Testing\Matching\TypeMatcher;
use JMac\Testing\Tests\Fixtures\Book;
use PHPUnit\Framework\TestCase;

final class ArgumentTest extends TestCase
{
    public function test_any_produces_an_any_matcher(): void
    {
        $this->assertInstanceOf(AnyMatcher::class, Argument::any());
    }

    public function test_type_produces_a_type_matcher_for_the_given_class(): void
    {
        $matcher = Argument::type(Book::class);

        $this->assertInstanceOf(TypeMatcher::class, $matcher);
        $this->assertTrue($matcher->matches(new Book('Some Title')));
    }

    public function test_satisfies_produces_a_predicate_matcher(): void
    {
        $matcher = Argument::satisfies(fn (int $id): bool => $id > 100);

        $this->assertInstanceOf(PredicateMatcher::class, $matcher);
        $this->assertTrue($matcher->matches(101));
        $this->assertFalse($matcher->matches(1));
    }

    public function test_capture_produces_a_matcher_that_matches_anything(): void
    {
        $captured = null;
        $matcher = Argument::capture($captured);

        $this->assertInstanceOf(Matcher::class, $matcher);
        $this->assertTrue($matcher->matches('anything'));
    }

    public function test_every_produced_matcher_implements_the_matcher_contract(): void
    {
        $captured = null;

        $this->assertInstanceOf(Matcher::class, Argument::any());
        $this->assertInstanceOf(Matcher::class, Argument::type(Book::class));
        $this->assertInstanceOf(Matcher::class, Argument::satisfies(fn (): bool => true));
        $this->assertInstanceOf(Matcher::class, Argument::capture($captured));
    }
}
