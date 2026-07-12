<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\Argument;
use JMac\Testing\Matching\ContainsMatcher;
use JMac\Testing\Matching\TypeMatcher;
use PHPUnit\Framework\TestCase;

final class ContainsMatcherTest extends TestCase
{
    public function test_matches_an_array_containing_the_bare_value(): void
    {
        $matcher = new ContainsMatcher('draft');

        $this->assertTrue($matcher->matches(['status' => 'draft', 'id' => 1]));
        $this->assertFalse($matcher->matches(['status' => 'published', 'id' => 1]));
    }

    public function test_matches_using_a_nested_matcher_against_any_element(): void
    {
        $matcher = new ContainsMatcher(new TypeMatcher('int'));

        $this->assertTrue($matcher->matches(['a', 'b', 3]));
        $this->assertFalse($matcher->matches(['a', 'b', 'c']));
    }

    public function test_matches_using_a_callback_given_value_and_key(): void
    {
        $matcher = new ContainsMatcher(fn (mixed $value, int|string $key): bool => $key === 'status' && $value === 'draft');

        $this->assertTrue($matcher->matches(['status' => 'draft', 'id' => 1]));
        $this->assertFalse($matcher->matches(['status' => 'published', 'id' => 1]));
    }

    public function test_matches_an_iterable_that_is_not_a_plain_array(): void
    {
        $matcher = new ContainsMatcher('draft');

        $generator = (function () {
            yield 'published';
            yield 'draft';
        })();

        $this->assertTrue($matcher->matches($generator));
    }

    public function test_does_not_match_a_non_iterable_value(): void
    {
        $matcher = new ContainsMatcher('draft');

        $this->assertFalse($matcher->matches('draft'));
        $this->assertFalse($matcher->matches(null));
    }

    public function test_describe_renders_the_wrapped_matchers_own_description_for_a_bare_value(): void
    {
        $this->assertSame("contains('draft')", (new ContainsMatcher('draft'))->describe());
    }

    public function test_describe_is_opaque_for_a_callback_same_as_satisfies(): void
    {
        $this->assertSame('contains(...)', (new ContainsMatcher(fn (): bool => true))->describe());
    }

    public function test_explain_mismatch_is_null_when_it_matches(): void
    {
        $this->assertNull((new ContainsMatcher('draft'))->explainMismatch(['draft']));
    }

    public function test_explain_mismatch_flags_a_non_iterable_actual_distinctly(): void
    {
        $matcher = new ContainsMatcher('draft');

        $this->assertSame('expected an iterable to search, got 5', $matcher->explainMismatch(5));
    }

    public function test_explain_mismatch_describes_both_sides_when_it_does_not_match(): void
    {
        $matcher = new ContainsMatcher('draft');

        $this->assertSame("expected contains('draft'), got array(1)", $matcher->explainMismatch(['published']));
    }

    public function test_argument_contains_produces_a_contains_matcher(): void
    {
        $matcher = Argument::contains('draft');

        $this->assertInstanceOf(ContainsMatcher::class, $matcher);
        $this->assertTrue($matcher->matches(['draft']));
        $this->assertFalse($matcher->matches(['published']));
    }
}
