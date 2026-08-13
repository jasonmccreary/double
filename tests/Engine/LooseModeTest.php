<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\PHPUnitExpectationCallMismatchException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitFabricationLimitExceededException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException;
use JMac\Testing\Integrations\PHPUnit\PHPUnitUnsatisfiedReceivedAssertionException;
use JMac\Testing\Tests\Support\Book;
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
        $double = Double::for(BookRepositoryInterface::class);

        $this->assertSame(0, $double->count());
    }

    public function test_void_return_produces_no_error(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsVoid());
    }

    public function test_bool_return_defaults_to_false(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertFalse($double->returnsBool());
    }

    public function test_int_return_defaults_to_zero(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame(0, $double->returnsInt());
    }

    public function test_float_return_defaults_to_zero_point_zero(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame(0.0, $double->returnsFloat());
    }

    public function test_string_return_defaults_to_empty_string(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame('', $double->returnsString());
    }

    public function test_array_return_defaults_to_empty_array(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame([], $double->returnsArray());
    }

    public function test_iterable_return_defaults_to_empty_array(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame([], $double->returnsIterable());
    }

    public function test_nullable_return_defaults_to_null(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsNullable());
    }

    public function test_mixed_return_defaults_to_null(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsMixed());
    }

    public function test_untyped_return_defaults_to_null(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsNoType());
    }

    public function test_self_return_defaults_to_the_double_itself(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame($double, $double->returnsSelf());
    }

    public function test_static_return_defaults_to_the_double_itself(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame($double, $double->returnsStatic());
    }

    public function test_enum_return_defaults_to_the_first_case(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame(Suit::Hearts, $double->returnsEnum());
    }

    public function test_union_return_defaults_to_the_first_branch(): void
    {
        // PHP's own Reflection does not preserve declaration order for a
        // union's members: `int|string` is reflected back out as `string`
        // then `int` (see SafeDefaultResolver::resolveUnion()), so the
        // "first" resolved default is '', not 0.
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertSame('', $double->returnsUnion());
    }

    public function test_nullable_union_return_prefers_null(): void
    {
        $double = Double::for(SafeDefaultInterface::class);

        $this->assertNull($double->returnsNullableUnion());
    }

    public function test_a_matched_expectation_with_no_configured_return_resolves_a_safe_default(): void
    {
        $double = Double::for(BookRepositoryInterface::class);
        $double->allows('count');

        $this->assertSame(0, $double->count());
    }

    /**
     * expects() holds its own method to a stricter standard than the
     * double's overall mode: once find() has an expects() registered, a
     * call that doesn't match it is always a mismatch to report, never a
     * call Loose mode is allowed to shrug off with a safe default.
     */
    public function test_an_expects_configured_method_throws_immediately_on_a_mismatched_call(): void
    {
        $double = Double::for(BookRepositoryInterface::class);
        $double->expects('find')->with(123)->returns(new Book('Dune'));

        try {
            $double->find(456);
            $this->fail('Expected PHPUnitExpectationCallMismatchException to be thrown.');
        } catch (PHPUnitExpectationCallMismatchException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString("received a call to `find(456)` that doesn't match", $message);
            $this->assertStringContainsString("Here's how it compares to the configured expectation for `find`:\n  id:\n    - 123\n    + 456", $message);
        }
    }

    /**
     * Two or more expectations registered for find() leaves no fact-based
     * way to say which one this call "should" have matched, so the message
     * lists every configured pattern instead of diffing against a guess.
     */
    public function test_an_expects_configured_method_lists_every_candidate_when_several_are_registered(): void
    {
        $double = Double::for(BookRepositoryInterface::class);
        $double->expects('find')->with(1)->returns(new Book('Dune'));
        $double->allows('find')->with(2)->returns(new Book('Dune Messiah'));

        try {
            $double->find(3);
            $this->fail('Expected PHPUnitExpectationCallMismatchException to be thrown.');
        } catch (PHPUnitExpectationCallMismatchException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString('This double has 2 expectations configured for `find`, but none match this call:', $message);
            $this->assertStringContainsString('`find(1)`', $message);
            $this->assertStringContainsString('`find(2)`', $message);
        }
    }

    /**
     * allows() alone never raises this method's bar — it's the optional
     * verb specifically because a mismatched call to it might just be an
     * incidental call the test doesn't care about, the same as a method
     * with nothing configured for it at all.
     */
    public function test_an_allows_only_configured_method_still_falls_back_to_a_safe_default_on_a_mismatched_call(): void
    {
        $double = Double::for(BookRepositoryInterface::class);
        $double->allows('find')->with(123)->returns(new Book('Dune'));

        $this->assertNull($double->find(456));
    }

    /**
     * expects()'s stricter standard doesn't relax once its own call-count
     * requirement is already met — the expectation stays registered for
     * find() for the rest of the test, so a later mismatched call still
     * reports a mismatch instead of quietly falling back to a default.
     */
    public function test_an_expects_configured_method_stays_strict_after_its_own_requirement_is_satisfied(): void
    {
        $double = Double::for(BookRepositoryInterface::class);
        $double->expects('find')->with(123)->returns(new Book('Dune'));

        $double->find(123);

        $this->expectException(PHPUnitExpectationCallMismatchException::class);

        $double->find(456);
    }

    public function test_a_self_referential_interface_return_defaults_to_the_double_itself(): void
    {
        // next(): NodeInterface names the same interface that declares it —
        // reflectively indistinguishable from `self` as of PHP 8.5 (see
        // SafeDefaultResolver's class docblock), so it resolves the same way:
        // the current double, not a distinct fabricated stand-in.
        $double = Double::for(NodeInterface::class);

        $this->assertSame($double, $double->next());
        $this->assertSame($double, $double->next()->next()->next());
    }

    public function test_verify_failure_on_a_fabricated_double_notes_its_provenance(): void
    {
        $double = Double::for(IntersectionReturnInterface::class);
        $fabricated = $double->make();
        $fabricated->expects('fill')->returns(true);

        try {
            $fabricated->verify();
            $this->fail('Expected UnsatisfiedExpectationException to be thrown.');
        } catch (PHPUnitUnsatisfiedExpectationException $exception) {
            $this->assertStringContainsString('this double was returned automatically', $exception->getMessage());
        }
    }

    public function test_received_failure_on_a_fabricated_double_notes_its_provenance(): void
    {
        $double = Double::for(IntersectionReturnInterface::class);
        $fabricated = $double->make();

        try {
            $fabricated->received('fill');
            $this->fail('Expected UnsatisfiedReceivedAssertionException to be thrown.');
        } catch (PHPUnitUnsatisfiedReceivedAssertionException $exception) {
            $this->assertStringContainsString('this double was returned automatically', $exception->getMessage());
        }
    }

    public function test_an_intersection_return_fabricates_a_double_implementing_all_constituents(): void
    {
        $double = Double::for(IntersectionReturnInterface::class);

        $result = $double->make();

        $this->assertInstanceOf(Fillable::class, $result);
        $this->assertInstanceOf(Sized::class, $result);
        $this->assertFalse($result->fill());
        $this->assertSame(0, $result->size());
    }

    public function test_a_single_unconfigured_fabrication_hop_is_free(): void
    {
        $double = Double::for(FirstLink::class);

        $second = $double->toSecond();

        $this->assertInstanceOf(SecondLink::class, $second);
    }

    public function test_a_second_distinct_fabrication_hop_throws_a_clear_limit_exception(): void
    {
        $double = Double::for(FirstLink::class);
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
