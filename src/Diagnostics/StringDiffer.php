<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * A structural comparison for MethodExpectation::describeMismatch(): a
 * plain "expected X, got Y" of two long, mostly-identical strings hides
 * whatever actually changed in a wall of shared text. This isolates just
 * the differing region(s) instead — line by line for a multi-line string
 * (JSON, SQL, a rendered template), character by character for a single
 * line — and elides everything shared around them.
 *
 * Both granularities run on the same underlying token comparison
 * (diffTokens(), a standard LCS) rather than two separate algorithms —
 * only what counts as a "token" (a line, or a character) differs. Character
 * rather than word granularity for the single-line case is deliberate: a
 * long token with no whitespace at all (a hash, an id, a base64 blob) has
 * no word boundary for a word-level diff to split on, and would otherwise
 * render as one giant unsplittable "changed" chunk.
 *
 * @internal
 */
final class StringDiffer
{
    /**
     * Below this combined length, a plain "expected X, got Y" (the full
     * values) is already easy to eyeball — a diff would save nothing and
     * would just be one more format for a short value.
     */
    public const MIN_LENGTH_TO_DIFF = 40;

    // Tokens of shared context kept on each side of a change before eliding
    // the rest with "…" — enough to orient the reader without reprinting
    // everything that didn't change.
    private const LINE_CONTEXT = 1;

    private const CHAR_CONTEXT = 12;

    // diffTokens() is O(tokens(expected) * tokens(actual)); past this many
    // tokens on either side, that stops being worth paying for and this
    // falls back to a plain, truncated comparison instead.
    private const MAX_TOKENS = 500;

    public static function diff(string $expected, string $actual): string
    {
        return str_contains($expected, "\n") || str_contains($actual, "\n")
            ? self::lineDiff($expected, $actual)
            : self::charDiff($expected, $actual);
    }

    private static function lineDiff(string $expected, string $actual): string
    {
        $expectedLines = explode("\n", $expected);
        $actualLines = explode("\n", $actual);

        if (count($expectedLines) > self::MAX_TOKENS || count($actualLines) > self::MAX_TOKENS) {
            return self::plain($expected, $actual);
        }

        $ops = self::diffTokens($expectedLines, $actualLines);
        $rendered = [];

        foreach ($ops as $index => [$type, $line]) {
            if ($type !== '=') {
                $rendered[] = ($type === '-' ? '- ' : '+ ').$line;

                continue;
            }

            if (self::withinContext($ops, $index, self::LINE_CONTEXT)) {
                $rendered[] = '  '.$line;
            } elseif ($rendered === [] || end($rendered) !== '  ...') {
                $rendered[] = '  ...';
            }
        }

        return implode("\n", $rendered);
    }

    private static function charDiff(string $expected, string $actual): string
    {
        // mb_str_split, not a byte-indexed split — a common byte-level
        // boundary could otherwise slice a multi-byte character in half.
        $expectedChars = mb_str_split($expected);
        $actualChars = mb_str_split($actual);

        if (count($expectedChars) > self::MAX_TOKENS || count($actualChars) > self::MAX_TOKENS) {
            return self::plain($expected, $actual);
        }

        $ops = self::diffTokens($expectedChars, $actualChars);

        return sprintf(
            "- %s\n+ %s",
            var_export(self::renderSide($ops, '-', self::CHAR_CONTEXT), true),
            var_export(self::renderSide($ops, '+', self::CHAR_CONTEXT), true),
        );
    }

    /**
     * @param  list<array{0: '='|'-'|'+', 1: string}>  $ops
     */
    private static function renderSide(array $ops, string $side, int $context): string
    {
        $out = '';
        $elided = false;

        foreach ($ops as $index => [$type, $token]) {
            if ($type !== '=' && $type !== $side) {
                continue; // the other side's exclusive change — not part of this rendering
            }

            if ($type === '=' && ! self::withinContext($ops, $index, $context)) {
                if (! $elided) {
                    $out .= '…';
                    $elided = true;
                }

                continue;
            }

            $out .= $token;
            $elided = false;
        }

        return $out;
    }

    /**
     * @param  list<array{0: '='|'-'|'+', 1: string}>  $ops
     */
    private static function withinContext(array $ops, int $index, int $radius): bool
    {
        $count = count($ops);

        for ($k = max(0, $index - $radius); $k <= min($count - 1, $index + $radius); $k++) {
            if ($ops[$k][0] !== '=') {
                return true;
            }
        }

        return false;
    }

    /**
     * The standard longest-common-subsequence edit script, used for both
     * lineDiff() (tokens are lines) and charDiff() (tokens are characters)
     * — the granularity differs, the algorithm doesn't.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: '='|'-'|'+', 1: string}>
     */
    private static function diffTokens(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $lengths = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $a[$i] === $b[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['=', $a[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $ops[] = ['-', $a[$i]];
                $i++;
            } else {
                $ops[] = ['+', $b[$j]];
                $j++;
            }
        }

        while ($i < $n) {
            $ops[] = ['-', $a[$i]];
            $i++;
        }

        while ($j < $m) {
            $ops[] = ['+', $b[$j]];
            $j++;
        }

        return $ops;
    }

    private static function plain(string $expected, string $actual): string
    {
        return sprintf(
            "- %s\n+ %s\n(too large to diff in detail)",
            var_export(self::truncate($expected), true),
            var_export(self::truncate($actual), true),
        );
    }

    private static function truncate(string $value, int $limit = 200): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'…' : $value;
    }
}
