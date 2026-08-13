<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 */
final class CallListFormatter
{
    // Uncapped, a method called many times legitimately (e.g. once per loop
    // iteration) turns a one-line diagnostic into a wall of text.
    private const CAP = 3;

    /**
     * Renders a capped "`method(args)`, `method(args)`, and N more" list.
     *
     * @param  list<string>  $callDescriptions  one pre-formatted argument list per call (the
     *                                          "(...)" part only — $method supplies the name)
     */
    public static function describe(string $method, array $callDescriptions): string
    {
        return self::capAndJoin(array_map(
            static fn (string $call): string => sprintf('`%s(%s)`', $method, $call),
            $callDescriptions,
        ));
    }

    /**
     * Same capped rendering as describe(), for Double::unused() — whose
     * calls span whatever methods were actually invoked, not one fixed
     * $method, so each entry already carries its own method name.
     *
     * @param  list<string>  $callDescriptions  one pre-formatted "method(args)" call per entry
     */
    public static function describeCalls(array $callDescriptions): string
    {
        return self::capAndJoin(array_map(
            static fn (string $call): string => sprintf('`%s`', $call),
            $callDescriptions,
        ));
    }

    /**
     * @param  list<string>  $rendered  already-backticked call descriptions
     */
    private static function capAndJoin(array $rendered): string
    {
        $total = count($rendered);
        $shown = $total <= self::CAP
            ? $rendered
            // Show CAP - 1, not CAP, once truncating: "3 of 4, and 1 more"
            // reads as arbitrary (why not just show the 4th?), where "and 2
            // more" reads as a deliberate summary.
            : array_slice($rendered, 0, self::CAP - 1);
        $remaining = $total - count($shown);

        $joined = implode(', ', $shown);

        if ($remaining > 0) {
            $joined .= sprintf(', and %d more', $remaining);
        }

        return $joined;
    }

    /**
     * @param  list<string>  $callDescriptions
     */
    public static function renderCorrelationParagraph(string $method, array $callDescriptions): string
    {
        // Trailing "\n" is deliberate — a new paragraph, not a continuation
        // of the sentence before it. See DoubleException::appendFabricatedNote().
        return sprintf(
            "The following calls to `%s` were made during this test: %s\n",
            $method,
            self::describe($method, $callDescriptions),
        );
    }

    /**
     * renderCorrelationParagraph()'s counterpart for the one case where the
     * observed call isn't just "a call that happened to this method" — it's
     * a fact-checked near miss: same method, same argument count, at least
     * one value off (see MethodExpectation::compareArguments()'s
     * 'comparisons' kind). One line per argument — its real parameter name,
     * then either its own value (unchanged) or a diff (changed) — instead
     * of a single line naming the whole call.
     *
     * $intro is the caller's own lead-in sentence — verify()'s never-satisfied
     * expectation (UnsatisfiedExpectationFields) and strict mode's immediate
     * rejection (UnexpectedCallFields) are describing two different
     * relationships (a past call that was similar vs. this call differing
     * from what's configured), so neither words it the same way.
     *
     * @param  list<ArgumentComparison>  $comparisons
     */
    public static function renderComparisonBlock(string $intro, array $comparisons): string
    {
        $lines = array_map(
            static fn (ArgumentComparison $comparison): string => $comparison->differs
                ? sprintf("  %s:\n%s", $comparison->label, self::indent($comparison->text, '    '))
                : sprintf('  %s: %s', $comparison->label, $comparison->text),
            $comparisons,
        );

        // Trailing "\n" is deliberate — a new paragraph, not a continuation
        // of the sentence before it. See DoubleException::appendFabricatedNote().
        return sprintf("%s\n%s\n", $intro, implode("\n", $lines));
    }

    private static function indent(string $text, string $prefix): string
    {
        return implode("\n", array_map(
            static fn (string $line): string => $prefix.$line,
            explode("\n", $text),
        ));
    }
}
