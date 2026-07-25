<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Diagnostics;

use JMac\Testing\Diagnostics\CallListFormatter;
use PHPUnit\Framework\TestCase;

final class CallListFormatterTest extends TestCase
{
    public function test_a_single_call_is_shown_with_no_truncation(): void
    {
        $this->assertSame('`find(1)`', CallListFormatter::describe('find', ['1']));
    }

    /**
     * The exact-cap boundary: at CAP (3) calls, every one is shown and
     * nothing is truncated — there's no "more" to speak of yet.
     */
    public function test_exactly_three_calls_are_all_shown_with_no_truncation(): void
    {
        $this->assertSame(
            '`find(1)`, `find(2)`, `find(3)`',
            CallListFormatter::describe('find', ['1', '2', '3']),
        );
    }

    /**
     * One call past the cap flips the behavior: only 2 are shown (CAP - 1),
     * not 3 — showing 3 of 4 and saying "and 1 more" would read as
     * arbitrary (why not just show the fourth?), where showing 2 and
     * saying "and 2 more" reads unambiguously as a deliberate summary.
     */
    public function test_one_call_past_the_cap_shows_only_two_and_summarizes_the_rest(): void
    {
        $this->assertSame(
            '`find(1)`, `find(2)`, and 2 more',
            CallListFormatter::describe('find', ['1', '2', '3', '4']),
        );
    }

    public function test_many_calls_past_the_cap_still_show_only_two(): void
    {
        $this->assertSame(
            '`find(1)`, `find(2)`, and 3 more',
            CallListFormatter::describe('find', ['1', '2', '3', '4', '5']),
        );
    }
}
