<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Matching\NotMatcher;
use JMac\Testing\Matching\TypeMatcher;
use PHPUnit\Framework\TestCase;

final class NotMatcherTest extends TestCase
{
    public function test_wraps_a_bare_literal_in_equals_matching(): void
    {
        $matcher = new NotMatcher(5);

        $this->assertFalse($matcher->matches(5));
        $this->assertTrue($matcher->matches(6));
    }

    public function test_negates_a_nested_matcher(): void
    {
        $matcher = new NotMatcher(new TypeMatcher('int'));

        $this->assertFalse($matcher->matches(5));
        $this->assertTrue($matcher->matches('5'));
    }

    public function test_describe_renders_the_wrapped_matchers_own_description(): void
    {
        $this->assertSame('not(5)', (new NotMatcher(5))->describe());
        $this->assertSame('not(type(int))', (new NotMatcher(new TypeMatcher('int')))->describe());
    }

    public function test_explain_mismatch_is_null_when_it_matches(): void
    {
        $this->assertNull((new NotMatcher(5))->explainMismatch(6));
    }

    public function test_explain_mismatch_describes_both_sides_when_it_does_not_match(): void
    {
        $matcher = new NotMatcher(5);

        $this->assertSame('expected anything but 5, got 5', $matcher->explainMismatch(5));
    }

    public function test_argument_not_produces_a_not_matcher(): void
    {
        $matcher = Argument::not(5);

        $this->assertInstanceOf(NotMatcher::class, $matcher);
        $this->assertTrue($matcher->matches(6));
        $this->assertFalse($matcher->matches(5));
    }
}
