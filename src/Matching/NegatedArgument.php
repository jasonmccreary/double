<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Returned by Argument::not() called with no argument — the entry point for
 * negating a matcher verb, so "not this type" reads left-to-right
 * (Argument::not()->type('int')) instead of nested inside-out.
 *
 * Not every Argument verb is mirrored here: capture() negated would
 * silently break its side effect (recordMatch() only fires capture() on a
 * matcher found via an instanceof CaptureMatcher check, which a NotMatcher
 * wrapper would hide), and remaining() is a positional with() marker, not a
 * per-value check, so negating it means nothing. Each verb kept here has an
 * unambiguous, useful negated meaning.
 */
final class NegatedArgument
{
    /**
     * @param  class-string|string  $type
     */
    public function type(string $type): Matcher
    {
        return new NotMatcher(Argument::type($type));
    }

    public function same(mixed $expected): Matcher
    {
        return new NotMatcher(Argument::same($expected));
    }

    /**
     * @param  callable(mixed): bool  $predicate
     */
    public function satisfies(callable $predicate): Matcher
    {
        return new NotMatcher(Argument::satisfies($predicate));
    }

    public function contains(mixed $needle): Matcher
    {
        return new NotMatcher(Argument::contains($needle));
    }

    public function matches(string $pattern): Matcher
    {
        return new NotMatcher(Argument::matches($pattern));
    }

    // not()->any($a, $b) reads as "none of these" for free, the same
    // composition every other verb here gets.
    public function any(mixed ...$alternatives): Matcher
    {
        return new NotMatcher(Argument::any(...$alternatives));
    }
}
