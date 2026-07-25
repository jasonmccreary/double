<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * See ARCHITECTURE.md, "Matcher." Every argument constraint — a literal
 * value, Argument::any(), a predicate closure, and any future matcher —
 * implements this one interface. Zero dependencies on the rest of the
 * library (see "Module boundaries"): a matcher only ever sees the value
 * it's being compared against, never Engine or Diagnostics types.
 *
 * Frozen, semver-guaranteed public contract, not just an internal detail
 * that happens to be reachable — implement this directly for a reusable,
 * named domain matcher instead of an anonymous Argument::satisfies()
 * closure. These three methods will not change or grow outside a major
 * version; a future capability need gets an additive optional interface
 * (e.g. ExplainsWithDetail extends Matcher) instead of widening this one.
 */
interface Matcher
{
    public function matches(mixed $actual): bool;

    public function describe(): string;

    /**
     * Null if it matched. A non-null result is prose explaining why it
     * didn't, for use by whatever assembles a diagnostic later (M3).
     */
    public function explainMismatch(mixed $actual): ?string;
}
