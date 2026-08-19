<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\EqualsMatcher;
use JMac\Testing\Tests\Support\Book;
use PHPUnit\Framework\TestCase;

final class EqualsMatcherTest extends TestCase
{
    public function test_matches_same_type_scalars_by_strict_equality(): void
    {
        $matcher = new EqualsMatcher(1);

        $this->assertTrue($matcher->matches(1));
        $this->assertFalse($matcher->matches(2));
    }

    public function test_rejects_cross_type_scalar_surprises(): void
    {
        $matcher = new EqualsMatcher(0);

        $this->assertFalse($matcher->matches('0'));
        $this->assertFalse($matcher->matches(false));
        $this->assertFalse($matcher->matches(null));
    }

    public function test_matches_arrays_by_strict_equality(): void
    {
        $matcher = new EqualsMatcher(['a' => 1]);

        $this->assertTrue($matcher->matches(['a' => 1]));
        $this->assertFalse($matcher->matches(['a' => '1']));
    }

    public function test_matches_objects_by_loose_equality(): void
    {
        $matcher = new EqualsMatcher(new Book('Some Title'));

        $this->assertTrue($matcher->matches(new Book('Some Title')));
        $this->assertFalse($matcher->matches(new Book('Other Title')));
    }

    public function test_matches_objects_nested_in_arrays_by_loose_equality(): void
    {
        $matcher = new EqualsMatcher(['book' => new Book('Some Title')]);

        $this->assertTrue($matcher->matches(['book' => new Book('Some Title')]));
        $this->assertFalse($matcher->matches(['book' => new Book('Other Title')]));
    }

    public function test_matches_objects_nested_multiple_arrays_deep_by_loose_equality(): void
    {
        $matcher = new EqualsMatcher([['book' => new Book('Some Title')]]);

        $this->assertTrue($matcher->matches([['book' => new Book('Some Title')]]));
        $this->assertFalse($matcher->matches([['book' => new Book('Other Title')]]));
    }

    public function test_describe_renders_the_expected_value(): void
    {
        $this->assertSame('123', (new EqualsMatcher(123))->describe());
        $this->assertSame("'baz'", (new EqualsMatcher('baz'))->describe());
        $this->assertSame('NULL', (new EqualsMatcher(null))->describe());
    }

    public function test_explain_mismatch_is_null_when_it_matches(): void
    {
        $this->assertNull((new EqualsMatcher(1))->explainMismatch(1));
    }

    public function test_explain_mismatch_describes_both_sides_when_it_does_not_match(): void
    {
        $matcher = new EqualsMatcher(123);

        $this->assertSame('expected 123, got 456', $matcher->explainMismatch(456));
    }
}
