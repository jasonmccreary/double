<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 *
 * Renders a capped "`method(args)`, `method(args)`, and N more" list — the
 * shared piece behind both call-correlation features (UnsatisfiedExpectation's
 * $otherObservedCalls and its symmetric extension on the unexpected-call
 * path). Each call is individually backtick-wrapped, matching how every other list
 * of code-like tokens in this codebase's messages is rendered (see
 * ReservedNameCollisionException::forCollisions()) — "and N more" itself
 * stays outside the backticks since it's prose, not a call.
 * Uncapped, a method called many times legitimately (e.g. once per loop
 * iteration with a different id each time) would turn a one-line diagnostic
 * into a wall of text with no more signal than the first few entries
 * already carry. Capping keeps the message the same shape regardless of how
 * many calls actually happened; the full, uncapped list still lives on the
 * exception's own public field for anything that wants it (these fields are
 * frozen public API, see Exceptions\TestDoubleException's docblock).
 *
 * At exactly CAP calls, all of them are shown and nothing is truncated —
 * there's no "more" to speak of. Past that, only CAP - 1 are shown, not
 * CAP: showing 3 of 4 and then saying "and 1 more" reads as arbitrary
 * (why not just show the fourth?), where showing 2 and saying "and 2 more"
 * reads unambiguously as a deliberate summary, not a near-complete list
 * that stopped one short.
 */
final class CallListFormatter
{
    private const CAP = 3;

    /**
     * @param  list<string>  $callDescriptions  one pre-formatted argument list per call (the
     *                                          "(...)" part only — $method supplies the name)
     */
    public static function describe(string $method, array $callDescriptions): string
    {
        $total = count($callDescriptions);
        $shown = $total <= self::CAP
            ? $callDescriptions
            : array_slice($callDescriptions, 0, self::CAP - 1);
        $remaining = $total - count($shown);

        $rendered = implode(', ', array_map(
            static fn (string $call): string => sprintf('`%s(%s)`', $method, $call),
            $shown,
        ));

        if ($remaining > 0) {
            $rendered .= sprintf(', and %d more', $remaining);
        }

        return $rendered;
    }

    /**
     * The full correlation paragraph both UnexpectedCallFields and
     * UnsatisfiedExpectationFields append — one implementation instead of
     * each hand-rolling the same "The following calls..." sentence, per this
     * class's own reason for existing. Ends in its own "\n" deliberately (a
     * new paragraph, not a continuation of the sentence before it) — see
     * TestDoubleException::appendFabricatedNote() for how that trailing
     * newline is reconciled with a note that might follow it.
     *
     * @param  list<string>  $callDescriptions
     */
    public static function renderCorrelationParagraph(string $method, array $callDescriptions): string
    {
        return sprintf(
            "The following calls to `%s` were made during this test: %s\n",
            $method,
            self::describe($method, $callDescriptions),
        );
    }
}
