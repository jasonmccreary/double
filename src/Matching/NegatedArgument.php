<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Returned by Argument::not() called with no argument — the entry point
 * for negating a matcher verb, as opposed to Argument::not($literal), which
 * negates a bare value directly. Exists so "not this type," "not this
 * pattern," etc. read left-to-right (Argument::not()->type('int')) instead
 * of nested inside-out (Argument::not(Argument::type('int'))) — see
 * NotMatcher's own docblock for why the nested form isn't offered as a
 * second spelling of the same thing.
 *
 * Deliberately not every Argument verb: capture() negated silently breaks
 * its side-effect (recordMatch() only fires capture() on a matcher found
 * via an instanceof CaptureMatcher check at the top level — wrapped in
 * NotMatcher, it's no longer that top-level matcher), and remaining() is a
 * positional with() marker, not a per-value check, so negating it doesn't
 * mean anything. Each verb kept here is included case-by-case because
 * negating it has an unambiguous, useful meaning — not because
 * completeness demands mirroring Argument's full list.
 *
 * any() is here too, but only its alternatives form does anything useful:
 * not()->any($a, $b) is notAnyOf($a, $b) — "none of these" — for free, the
 * same composition every other verb here already gets. not()->any() with
 * zero alternatives is a degenerate "matches nothing" (negating "matches
 * everything"), same as any() itself is a degenerate no-op at that arity —
 * not specially guarded against, any more than any() itself special-cases
 * being called for no reason.
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

    public function any(mixed ...$alternatives): Matcher
    {
        return new NotMatcher(Argument::any(...$alternatives));
    }
}
