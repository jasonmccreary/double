<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Diagnostics;

use JMac\Testing\Diagnostics\ArgumentComparison;
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

    /**
     * describeCalls() is describe()'s counterpart for unused(): each
     * entry already carries its own method name, since the calls it
     * describes span whatever methods were actually invoked, not one fixed
     * method — same capping rule, applied to already-rendered "method(args)"
     * strings instead of args-only ones.
     */
    public function test_a_single_call_across_methods_is_shown_with_no_truncation(): void
    {
        $this->assertSame('`find(1)`', CallListFormatter::describeCalls(['find(1)']));
    }

    public function test_calls_across_different_methods_are_all_shown_up_to_the_cap(): void
    {
        $this->assertSame(
            '`find(1)`, `save(2)`, `delete(3)`',
            CallListFormatter::describeCalls(['find(1)', 'save(2)', 'delete(3)']),
        );
    }

    public function test_calls_across_different_methods_past_the_cap_summarize_the_rest(): void
    {
        $this->assertSame(
            '`find(1)`, `save(2)`, and 2 more',
            CallListFormatter::describeCalls(['find(1)', 'save(2)', 'delete(3)', 'close(4)']),
        );
    }

    public function test_render_comparison_block_shows_a_non_diff_entry_on_one_line(): void
    {
        $this->assertSame(
            "Intro:\n  id: 42\n",
            CallListFormatter::renderComparisonBlock('Intro:', [
                new ArgumentComparison(label: 'id', differs: false, text: '42'),
            ]),
        );
    }

    /**
     * A diff entry's -/+ pair is indented one level further than its own
     * label — inset from it, not flush with it — so the label reads as a
     * header for the diff nested under it rather than a sibling line.
     */
    public function test_render_comparison_block_insets_a_diff_entrys_lines_under_its_label(): void
    {
        $this->assertSame(
            "Intro:\n  id:\n    - 42\n    + 43\n",
            CallListFormatter::renderComparisonBlock('Intro:', [
                new ArgumentComparison(label: 'id', differs: true, text: "- 42\n+ 43"),
            ]),
        );
    }

    /**
     * Matching arguments stay plain, one-line entries for context; only the
     * differing one gets the label-on-its-own-line diff treatment — so a
     * multi-argument call reads as one block, not a wall of diffs.
     */
    public function test_render_comparison_block_mixes_context_and_diff_entries(): void
    {
        $this->assertSame(
            "Intro:\n  name:\n    - 'baz'\n    + 'Baz'\n  status: 'y'\n",
            CallListFormatter::renderComparisonBlock('Intro:', [
                new ArgumentComparison(label: 'name', differs: true, text: "- 'baz'\n+ 'Baz'"),
                new ArgumentComparison(label: 'status', differs: false, text: "'y'"),
            ]),
        );
    }

    /**
     * A multi-line diff (StringDiffer's line-by-line output for a JSON body,
     * say) gets every one of its own lines inset under the label, not
     * just the first.
     */
    public function test_render_comparison_block_indents_every_line_of_a_multi_line_diff(): void
    {
        $this->assertSame(
            "Intro:\n  body:\n      ...\n    -   line two\n    +   LINE TWO\n      ...\n",
            CallListFormatter::renderComparisonBlock('Intro:', [
                new ArgumentComparison(label: 'body', differs: true, text: "  ...\n-   line two\n+   LINE TWO\n  ..."),
            ]),
        );
    }
}
