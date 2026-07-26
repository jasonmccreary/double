<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Every argument constraint — a literal value, Argument::any(), a predicate
 * closure, and any future matcher — implements this one interface.
 *
 * Frozen, semver-guaranteed public contract — implement this directly for a
 * reusable, named domain matcher instead of an anonymous
 * Argument::satisfies() closure. A future capability need gets an additive
 * optional interface (e.g. ExplainsWithDetail extends Matcher) instead of
 * widening this one.
 */
interface Matcher
{
    public function matches(mixed $actual): bool;

    public function describe(): string;

    /**
     * Null if it matched. A non-null result is prose explaining why it
     * didn't, for use by whatever assembles a diagnostic message.
     */
    public function explainMismatch(mixed $actual): ?string;
}
