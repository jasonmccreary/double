<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\TestDouble;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\Tests\Fixtures\BookRepositoryInterface;
use JMac\Testing\Tests\Fixtures\Fillable;
use JMac\Testing\Tests\Fixtures\IntersectionReturnInterface;
use JMac\Testing\Tests\Fixtures\NodeInterface;
use JMac\Testing\Tests\Fixtures\SafeDefaultInterface;
use JMac\Testing\Tests\Fixtures\Sized;
use JMac\Testing\Tests\Fixtures\Suit;
use PHPUnit\Framework\TestCase;

final class LooseModeTest extends TestCase
{
    public function test_an_unconfigured_double_defaults_to_loose_mode(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->assertSame(0, $double->count());
    }

    public function test_void_return_produces_no_error(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsVoid());
    }

    public function test_bool_return_defaults_to_false(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertFalse($double->returnsBool());
    }

    public function test_int_return_defaults_to_zero(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame(0, $double->returnsInt());
    }

    public function test_float_return_defaults_to_zero_point_zero(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame(0.0, $double->returnsFloat());
    }

    public function test_string_return_defaults_to_empty_string(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame('', $double->returnsString());
    }

    public function test_array_return_defaults_to_empty_array(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame([], $double->returnsArray());
    }

    public function test_iterable_return_defaults_to_empty_array(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame([], $double->returnsIterable());
    }

    public function test_nullable_return_defaults_to_null(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsNullable());
    }

    public function test_mixed_return_defaults_to_null(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsMixed());
    }

    public function test_untyped_return_defaults_to_null(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsNoType());
    }

    public function test_self_return_defaults_to_the_double_itself(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame($double, $double->returnsSelf());
    }

    public function test_static_return_defaults_to_the_double_itself(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame($double, $double->returnsStatic());
    }

    public function test_enum_return_defaults_to_the_first_case(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame(Suit::Hearts, $double->returnsEnum());
    }

    public function test_union_return_defaults_to_the_first_branch(): void
    {
        // PHP's own Reflection does not preserve declaration order for a
        // union's members: `int|string` is reflected back out as `string`
        // then `int` (see SafeDefaultResolver::resolveUnion()), so the
        // "first" resolved default is '', not 0.
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertSame('', $double->returnsUnion());
    }

    public function test_nullable_union_return_prefers_null(): void
    {
        $double = TestDouble::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsNullableUnion());
    }

    public function test_a_matched_expectation_with_no_configured_return_resolves_a_safe_default(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);
        $double->allows('count');

        $this->assertSame(0, $double->count());
    }

    public function test_a_non_nullable_interface_return_fabricates_a_recursive_stand_in(): void
    {
        $double = TestDouble::for(NodeInterface::class);

        $level1 = $double->next();
        $this->assertInstanceOf(NodeInterface::class, $level1);
        $this->assertTrue(TestDouble::stateFor($level1)->isFabricated());
        $this->assertSame(1, TestDouble::stateFor($level1)->fabricationDepth());

        $level2 = $level1->next();
        $this->assertInstanceOf(NodeInterface::class, $level2);
        $this->assertSame(2, TestDouble::stateFor($level2)->fabricationDepth());
    }

    public function test_recursive_fabrication_stops_at_the_depth_cap_by_closing_the_cycle(): void
    {
        // Past the depth cap, null isn't viable — next(): NodeInterface is
        // non-nullable, and PHP would throw a TypeError the instant a safe
        // default tried to return null there. Instead, the double at the
        // cap (level2) is reused to close the cycle rather than fabricating
        // a distinct level3 (see SafeDefaultResolver's class docblock).
        $double = TestDouble::for(NodeInterface::class);

        $level1 = $double->next();
        $level2 = $level1->next();
        $level3 = $level2->next();

        $this->assertSame($level2, $level3);
    }

    public function test_verify_failure_on_a_fabricated_double_notes_its_provenance(): void
    {
        $double = TestDouble::for(NodeInterface::class);
        $fabricated = $double->next();
        $fabricated->expects('next')->returns($fabricated);

        try {
            TestDouble::verify($fabricated);
            $this->fail('Expected UnsatisfiedExpectationException to be thrown.');
        } catch (UnsatisfiedExpectationException $exception) {
            $this->assertStringContainsString('auto-fabricated', $exception->getMessage());
        }
    }

    public function test_an_intersection_return_fabricates_a_double_implementing_all_constituents(): void
    {
        $double = TestDouble::for(IntersectionReturnInterface::class);

        $result = $double->make();

        $this->assertInstanceOf(Fillable::class, $result);
        $this->assertInstanceOf(Sized::class, $result);
        $this->assertFalse($result->fill());
        $this->assertSame(0, $result->size());
    }
}
