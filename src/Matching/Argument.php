<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * The argument-matcher facade (see ARCHITECTURE.md, "Verb lineage").
 * Starting matcher set — any(), type(), satisfies() — deliberately minimal
 * for v1; more get added once real usage shows what's actually needed,
 * rather than porting a full matcher catalog speculatively. capture() and
 * remaining() are later additions past that starting set, added once a
 * concrete need showed up rather than speculatively.
 */
final class Argument
{
    private function __construct() {}

    public static function any(): Matcher
    {
        return new AnyMatcher;
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
}
