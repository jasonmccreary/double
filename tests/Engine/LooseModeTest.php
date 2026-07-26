<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Integrations\PHPUnit\PHPUnitFabricationLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException;
use JMac\Testing\TestDouble;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use JMac\Testing\Tests\Support\Fillable;
use JMac\Testing\Tests\Support\FirstLink;
use JMac\Testing\Tests\Support\IntersectionReturnInterface;
use JMac\Testing\Tests\Support\NodeInterface;
use JMac\Testing\Tests\Support\SafeDefaultInterface;
use JMac\Testing\Tests\Support\SecondLink;
use JMac\Testing\Tests\Support\Sized;
use JMac\Testing\Tests\Support\Suit;
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

    public function test_a_self_referential_interface_return_defaults_to_the_double_itself(): void
    {
        // next(): NodeInterface names the same interface that declares it —
        // reflectively indistinguishable from `self` as of PHP 8.5 (see
        // SafeDefaultResolver's class docblock), so it resolves the same way:
        // the current double, not a distinct fabricated stand-in.
        $double = TestDouble::for(NodeInterface::class);

        $this->assertSame($double, $double->next());
        $this->assertSame($double, $double->next()->next()->next());
    }

    public function test_verify_failure_on_a_fabricated_double_notes_its_provenance(): void
    {
        $double = TestDouble::for(IntersectionReturnInterface::class);
        $fabricated = $double->make();
        $fabricated->expects('fill')->returns(true);

        try {
            $fabricated->verify();
            $this->fail('Expected UnsatisfiedExpectationException to be thrown.');
        } catch (PHPUnitUnsatisfiedExpectationException $exception) {
            $this->assertStringContainsString('this test double was returned automatically', $exception->getMessage());
        }
    }

    public function test_received_failure_on_a_fabricated_double_notes_its_provenance(): void
    {
        $double = TestDouble::for(IntersectionReturnInterface::class);
        $fabricated = $double->make();

        try {
            $fabricated->received('fill');
            $this->fail('Expected UnsatisfiedReceivedAssertionException to be thrown.');
        } catch (PHPUnitUnsatisfiedReceivedAssertionException $exception) {
            $this->assertStringContainsString('this test double was returned automatically', $exception->getMessage());
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

    public function test_a_single_unconfigured_fabrication_hop_is_free(): void
    {
        $double = TestDouble::for(FirstLink::class);

        $second = $double->toSecond();

        $this->assertInstanceOf(SecondLink::class, $second);
    }

    public function test_a_second_distinct_fabrication_hop_throws_a_clear_limit_exception(): void
    {
        $double = TestDouble::for(FirstLink::class);
        $second = $double->toSecond();

        try {
            $second->toThird();
            $this->fail('Expected PHPUnitFabricationLimitExceededException to be thrown.');
        } catch (PHPUnitFabricationLimitExceededException $exception) {
            $this->assertStringContainsString('SecondLink', $exception->getMessage());
            $this->assertStringContainsString('one level deep', $exception->getMessage());
            $this->assertStringContainsString(
                "\$secondLink->allows('toThird')->returns(\$anotherDouble)",
                $exception->getMessage(),
            );
        }
    }
}
