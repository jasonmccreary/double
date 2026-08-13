<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Diagnostics;

use JMac\Testing\Diagnostics\StringDiffer;
use PHPUnit\Framework\TestCase;

final class StringDifferTest extends TestCase
{
    public function test_short_strings_are_shown_in_full_with_no_ellipsis(): void
    {
        $this->assertSame(
            "- 'hello world'\n+ 'hello earth'",
            StringDiffer::diff('hello world', 'hello earth'),
        );
    }

    /**
     * The motivating single-line case: a long shared prefix and suffix
     * around a small differing region collapses to a windowed snippet
     * instead of two full dumps of otherwise-identical text.
     */
    public function test_a_difference_surrounded_by_shared_text_is_windowed_with_ellipsis_on_both_sides(): void
    {
        $expected = str_repeat('a', 30).'baz'.str_repeat('a', 30);
        $actual = str_repeat('a', 30).'BAZ'.str_repeat('a', 30);

        $this->assertSame(
            "- '…aaaaaaaaaaaabazaaaaaaaaaaaa…'\n+ '…aaaaaaaaaaaaBAZaaaaaaaaaaaa…'",
            StringDiffer::diff($expected, $actual),
        );
    }

    public function test_a_difference_at_the_very_start_has_no_leading_ellipsis(): void
    {
        $expected = 'baz'.str_repeat('a', 30);
        $actual = 'BAZ'.str_repeat('a', 30);

        $this->assertSame(
            "- 'bazaaaaaaaaaaaa…'\n+ 'BAZaaaaaaaaaaaa…'",
            StringDiffer::diff($expected, $actual),
        );
    }

    public function test_a_difference_at_the_very_end_has_no_trailing_ellipsis(): void
    {
        $expected = str_repeat('a', 30).'baz';
        $actual = str_repeat('a', 30).'BAZ';

        $this->assertSame(
            "- '…aaaaaaaaaaaabaz'\n+ '…aaaaaaaaaaaaBAZ'",
            StringDiffer::diff($expected, $actual),
        );
    }

    public function test_completely_different_strings_have_nothing_to_elide(): void
    {
        $this->assertSame(
            "- 'foo'\n+ 'bar'",
            StringDiffer::diff('foo', 'bar'),
        );
    }

    /**
     * Character, not word, granularity for the single-line case is
     * deliberate: a long token with no whitespace at all (a hash, an id, a
     * base64 blob) has no word boundary for a word-level diff to split on,
     * and would otherwise render as one giant unsplittable "changed" chunk.
     */
    public function test_a_single_long_token_with_no_whitespace_still_gets_windowed(): void
    {
        $expected = str_repeat('a', 30).'deadbeef'.str_repeat('a', 30);
        $actual = str_repeat('a', 30).'cafebabe'.str_repeat('a', 30);

        $this->assertSame(
            "- '…aaaaaaaaaaaadeadbeefaaaaaaaaaaaa…'\n+ '…aaaaaaaaaaaacafebabeaaaaaaaaaaaa…'",
            StringDiffer::diff($expected, $actual),
        );
    }

    /**
     * Two separate differences far apart in one long line each get their
     * own small window — the LCS comparison isolates both changed regions
     * instead of treating everything between the first and last difference
     * as one indivisible chunk.
     */
    public function test_two_separate_differences_in_one_line_are_each_windowed_independently(): void
    {
        $expected = 'Dear customer, your order #baz has shipped and will arrive via ground on Tuesday, thanks for your business!';
        $actual = 'Dear customer, your order #qux has shipped and will arrive via ground on Friday, thanks for your business!';

        $this->assertSame(
            "- '…your order #baz has shipped…a ground on Tuesday, thanks …'\n+ '…your order #qux has shipped…a ground on Friday, thanks …'",
            StringDiffer::diff($expected, $actual),
        );
    }

    /**
     * mb_str_split, not a byte-indexed comparison — a byte-level common
     * boundary could otherwise slice a multi-byte character in half and
     * corrupt it in the rendered snippet.
     */
    public function test_multi_byte_characters_are_never_split(): void
    {
        $this->assertSame(
            "- '🙂🙂🙂A🙂🙂🙂'\n+ '🙂🙂🙂B🙂🙂🙂'",
            StringDiffer::diff('🙂🙂🙂A🙂🙂🙂', '🙂🙂🙂B🙂🙂🙂'),
        );
    }

    /**
     * A multi-line string (JSON, SQL, a rendered template) diffs line by
     * line rather than as one giant blob with a raw newline embedded in a
     * single-quoted string — only the changed line, plus a line of
     * context on each side, survives; the rest collapses to "...".
     */
    public function test_multi_line_strings_diff_line_by_line(): void
    {
        $expected = "{\n    \"id\": 1,\n    \"name\": \"baz\",\n    \"active\": true,\n    \"tags\": [\"a\", \"b\"]\n}";
        $actual = "{\n    \"id\": 1,\n    \"name\": \"Baz\",\n    \"active\": true,\n    \"tags\": [\"a\", \"b\"]\n}";

        $this->assertSame(
            "  ...\n      \"id\": 1,\n-     \"name\": \"baz\",\n+     \"name\": \"Baz\",\n      \"active\": true,\n  ...",
            StringDiffer::diff($expected, $actual),
        );
    }

    public function test_a_multi_line_diff_marks_an_inserted_line_with_no_removed_counterpart(): void
    {
        $expected = "line one\nline two\nline four";
        $actual = "line one\nline two\nline three\nline four";

        $this->assertSame(
            "  ...\n  line two\n+ line three\n  line four",
            StringDiffer::diff($expected, $actual),
        );
    }

    /**
     * The O(tokens²) comparison bails out past MAX_TOKENS rather than
     * paying for it on pathologically large input — a truncated plain
     * comparison instead of a detailed diff.
     */
    public function test_extremely_long_single_line_input_falls_back_to_a_truncated_plain_comparison(): void
    {
        $expected = str_repeat('a', 600);
        $actual = str_repeat('b', 600);

        $result = StringDiffer::diff($expected, $actual);

        $this->assertStringContainsString('(too large to diff in detail)', $result);
        $this->assertStringContainsString('…', $result);
    }
}
