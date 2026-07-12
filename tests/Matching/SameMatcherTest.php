<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\SameMatcher;
use JMac\Testing\Tests\Support\Book;
use PHPUnit\Framework\TestCase;

final class SameMatcherTest extends TestCase
{
    public function test_matches_only_the_exact_same_instance(): void
    {
        $book = new Book('Some Title');
        $matcher = new SameMatcher($book);

        $this->assertTrue($matcher->matches($book));
        $this->assertFalse($matcher->matches(new Book('Some Title')));
    }

    public function test_matches_scalars_by_strict_equality_too(): void
    {
        $matcher = new SameMatcher(0);

        $this->assertTrue($matcher->matches(0));
        $this->assertFalse($matcher->matches('0'));
        $this->assertFalse($matcher->matches(false));
    }

    public function test_describe_renders_the_expected_value(): void
    {
        $this->assertSame('123', (new SameMatcher(123))->describe());
    }

    public function test_explain_mismatch_is_null_when_it_matches(): void
    {
        $this->assertNull((new SameMatcher(1))->explainMismatch(1));
    }

    public function test_explain_mismatch_describes_both_sides_when_it_does_not_match(): void
    {
        $matcher = new SameMatcher(123);

        $this->assertSame('expected the same 123, got 456', $matcher->explainMismatch(456));
    }
}
