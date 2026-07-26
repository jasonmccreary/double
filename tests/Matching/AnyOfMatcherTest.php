<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\AnyOfMatcher;
use JMac\Testing\Matching\Argument;
use JMac\Testing\Matching\TypeMatcher;
use PHPUnit\Framework\TestCase;

final class AnyOfMatcherTest extends TestCase
{
    public function test_matches_any_of_several_bare_literals(): void
    {
        $matcher = new AnyOfMatcher(['draft', 'published', 'archived']);

        $this->assertTrue($matcher->matches('draft'));
        $this->assertTrue($matcher->matches('published'));
        $this->assertFalse($matcher->matches('deleted'));
    }

    public function test_matches_using_nested_matchers_as_alternatives(): void
    {
        $matcher = new AnyOfMatcher([new TypeMatcher('int'), new TypeMatcher('float')]);

        $this->assertTrue($matcher->matches(5));
        $this->assertTrue($matcher->matches(5.0));
        $this->assertFalse($matcher->matches('5'));
    }

    public function test_matches_using_a_mix_of_literals_and_matchers(): void
    {
        $matcher = new AnyOfMatcher(['draft', new TypeMatcher('int')]);

        $this->assertTrue($matcher->matches('draft'));
        $this->assertTrue($matcher->matches(5));
        $this->assertFalse($matcher->matches('published'));
    }

    public function test_describe_renders_every_alternative(): void
    {
        $matcher = new AnyOfMatcher(['draft', 'published']);

        $this->assertSame("any('draft', 'published')", $matcher->describe());
    }

    public function test_explain_mismatch_is_null_when_it_matches(): void
    {
        $matcher = new AnyOfMatcher(['draft', 'published']);

        $this->assertNull($matcher->explainMismatch('draft'));
    }

    public function test_explain_mismatch_describes_both_sides_when_it_does_not_match(): void
    {
        $matcher = new AnyOfMatcher(['draft', 'published']);

        $this->assertSame(
            "expected any('draft', 'published'), got 'deleted'",
            $matcher->explainMismatch('deleted'),
        );
    }

    public function test_argument_any_with_no_arguments_still_matches_everything(): void
    {
        $matcher = Argument::any();

        $this->assertTrue($matcher->matches('anything'));
        $this->assertTrue($matcher->matches(null));
        $this->assertSame('any()', $matcher->describe());
    }

    public function test_argument_any_with_arguments_produces_an_any_of_matcher(): void
    {
        $matcher = Argument::any('draft', 'published');

        $this->assertInstanceOf(AnyOfMatcher::class, $matcher);
        $this->assertTrue($matcher->matches('draft'));
        $this->assertFalse($matcher->matches('deleted'));
    }
}
