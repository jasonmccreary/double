<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * The argument-matcher facade (see ARCHITECTURE.md, "Verb lineage").
 * Starting matcher set — any(), type(), satisfies() — deliberately minimal
 * for v1; more get added once real usage shows what's actually needed,
 * rather than porting a full matcher catalog speculatively. capture(),
 * remaining(), same(), not(), matches(), contains(), and none() are later
 * additions past that starting set, added once a concrete need showed up
 * rather than speculatively. not(), matches(), and contains() specifically
 * close the gap where satisfies() was the only escape hatch for "not this
 * value," "matches this format," and "has this element" — a gap that cost
 * this library its own diagnostic-quality standard every time it was used,
 * since satisfies()'s describe() can only ever render the opaque
 * "satisfies(...)", never what the predicate actually checked.
 *
 * any() with one or more arguments closes the last one of these gaps —
 * "any of these specific values" — as a widening of any()'s own existing
 * meaning rather than a new verb: no arguments still means "no constraint
 * at all," one or more narrows that domain to the given alternatives. No
 * separate anyOf()/notAnyOf() verbs exist because they'd be redundant with
 * any($a, $b) and not()->any($a, $b) respectively.
 */
final class Argument
{
    private function __construct() {}

    /**
     * No arguments — the original, unconstrained any(): matches literally
     * everything, including null. One or more arguments — any($a, $b, ...):
     * matches if any one of the given alternatives matches, each following
     * the same bare-literal-or-Matcher rule as with()/not()/contains(). Not
     * a semantic flip between the two forms, just a domain that defaults to
     * "everything" and narrows once you name specific alternatives — the
     * same "any of these" a specified list already means in plain English.
     * See AnyOfMatcher's own docblock for why the two forms stay separate
     * classes rather than one "possibly-empty alternatives" class.
     */
    public static function any(mixed ...$alternatives): Matcher
    {
        return $alternatives === [] ? new AnyMatcher : new AnyOfMatcher($alternatives);
    }

    /**
     * $type is either a class/interface name (matches via instanceof) or a
     * PHP builtin type name — 'int', 'float', 'string', 'bool', 'array',
     * 'object', 'callable', 'iterable', 'null', 'mixed' — matched via the
     * corresponding is_*() check (see TypeMatcher).
     *
     * @param  class-string|string  $type
     */
    public static function type(string $type): Matcher
    {
        return new TypeMatcher($type);
    }

    /**
     * @param  callable(mixed): bool  $predicate
     */
    public static function satisfies(callable $predicate): Matcher
    {
        return new PredicateMatcher($predicate);
    }

    /**
     * Matches any value, like any(), and writes it into $reference once the
     * expectation it's attached to is confirmed as the real match for a
     * call — mirrors Mockery::capture(). $reference only ever holds the
     * most recently matched call's value.
     */
    public static function capture(mixed &$reference): Matcher
    {
        return new CaptureMatcher($reference);
    }

    /**
     * A trailing with() marker: everything from this position to the end
     * of the actual call's arguments is unconstrained, however many there
     * turn out to be — see RemainingMatcher. Only valid as the last
     * argument passed to with(); with() throws otherwise.
     */
    public static function remaining(): Matcher
    {
        return new RemainingMatcher;
    }

    /**
     * Asserts the call took no arguments at all — see NoneMatcher and
     * ARCHITECTURE.md's "Argument::satisfies()" section for why this isn't
     * a bare with(), and why it isn't a class constant either. Only valid
     * as the sole argument passed to with(); with() throws otherwise.
     */
    public static function none(): Matcher
    {
        return new NoneMatcher;
    }

    /**
     * Matches only the exact same instance (===), for the one case a bare
     * literal passed to with() doesn't cover: object identity. A bare
     * literal object still defaults to == ("an equivalent value") — see
     * SameMatcher and ARCHITECTURE.md's "Strict-by-default scalar/array
     * matching, loose objects, explicit object identity." Named after
     * PHPUnit's own assertSame(), not php.net's operator-name table.
     */
    public static function same(mixed $expected): Matcher
    {
        return new SameMatcher($expected);
    }

    /**
     * Two distinct shapes, disambiguated by whether an argument was passed
     * at all (func_num_args(), not a null-check — not(null) is a legitimate
     * "not null" literal match, not the zero-arg case):
     *
     * - not($literal) — negates a bare value directly, e.g. not(5) matches
     *   anything but 5. $literal follows with()'s own rule (wrapped in
     *   EqualsMatcher).
     * - not() — no argument, returns a NegatedArgument for negating a verb
     *   instead of a literal: not()->type('int'), not()->contains(5). See
     *   NegatedArgument's own docblock for which verbs it offers and why.
     *
     * A Matcher passed directly to the one-argument form is rejected rather
     * than silently accepted as a second spelling of not()->verb(...) —
     * exactly one canonical way to negate a matcher, not two.
     */
    public static function not(mixed $expected = null): Matcher|NegatedArgument
    {
        if (func_num_args() === 0) {
            return new NegatedArgument;
        }

        if ($expected instanceof Matcher) {
            throw new \InvalidArgumentException(
                'You can\'t use `Argument::not($matcher)` directly — it must prefix a matcher instead. '
                .'For example: `Argument::not()->type(\'int\')` or `Argument::not()->contains($needle)`.',
            );
        }

        return new NotMatcher($expected);
    }

    /**
     * Matches a string (or Stringable) argument against a PCRE pattern —
     * $pattern includes its own delimiters, e.g. Argument::matches('/^\d+$/'),
     * the same convention preg_match() itself uses. Named matches(), not
     * pattern(): the regular expression already is the pattern, so the verb
     * should name the action (matching against it), not restate the noun.
     * See PatternMatcher's own docblock for why non-string values are a
     * straightforward non-match rather than an uncontrolled (string) cast.
     */
    public static function matches(string $pattern): Matcher
    {
        return new PatternMatcher($pattern);
    }

    /**
     * Matches an iterable (array or Traversable) argument containing at
     * least one element satisfying $needle — a Matcher (any element
     * matches it), a plain callable invoked as ($value, $key) per element,
     * or a bare literal (wrapped in EqualsMatcher, same rule as with()
     * itself). See ContainsMatcher's own docblock for why these three
     * forms cover what Mockery needs separate contains()/hasKey()/
     * hasValue()/subset() verbs for for.
     */
    public static function contains(mixed $needle): Matcher
    {
        return new ContainsMatcher($needle);
    }
}
