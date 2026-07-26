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
     * Same capped rendering as describe(), for TestDouble::unused() — whose
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
        // of the sentence before it. See TestDoubleException::appendFabricatedNote().
        return sprintf(
            "The following calls to `%s` were made during this test: %s\n",
            $method,
            self::describe($method, $callDescriptions),
        );
    }
}
