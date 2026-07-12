<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Matching\PatternMatcher;
use PHPUnit\Framework\TestCase;

final class PatternMatcherTest extends TestCase
{
    public function test_matches_a_string_against_the_pattern(): void
    {
        $matcher = new PatternMatcher('/^\d+$/');

        $this->assertTrue($matcher->matches('12345'));
        $this->assertFalse($matcher->matches('12a45'));
    }

    public function test_matches_a_stringable_object(): void
    {
        $matcher = new PatternMatcher('/^\d+$/');
        $stringable = new class implements \Stringable
        {
            public function __toString(): string
            {
                return '12345';
            }
        };

        $this->assertTrue($matcher->matches($stringable));
    }

    public function test_does_not_match_a_non_string_non_stringable_value(): void
    {
        $matcher = new PatternMatcher('/^\d+$/');

        $this->assertFalse($matcher->matches(12345));
        $this->assertFalse($matcher->matches(['12345']));
        $this->assertFalse($matcher->matches(null));
    }

    public function test_rejects_an_invalid_pattern_at_construction_time(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid regular expression');

        new PatternMatcher('/unterminated');
    }

    public function test_describe_renders_the_pattern(): void
    {
        $this->assertSame('pattern(/^\d+$/)', (new PatternMatcher('/^\d+$/'))->describe());
    }

    public function test_explain_mismatch_is_null_when_it_matches(): void
    {
        $this->assertNull((new PatternMatcher('/^\d+$/'))->explainMismatch('123'));
    }

    public function test_explain_mismatch_describes_both_sides_when_it_does_not_match(): void
    {
        $matcher = new PatternMatcher('/^\d+$/');

        $this->assertSame("expected to match pattern /^\\d+$/, got 'abc'", $matcher->explainMismatch('abc'));
    }

    public function test_argument_matches_produces_a_pattern_matcher(): void
    {
        $matcher = Argument::matches('/^\d+$/');

        $this->assertInstanceOf(PatternMatcher::class, $matcher);
        $this->assertTrue($matcher->matches('123'));
        $this->assertFalse($matcher->matches('abc'));
    }
}
