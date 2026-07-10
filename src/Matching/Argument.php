<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * The argument-matcher facade (see ARCHITECTURE.md, "Verb lineage").
 * Starting matcher set only — any(), type(), that() — deliberately minimal
 * for v1; more get added once real usage shows what's actually needed,
 * rather than porting a full matcher catalog speculatively. capture() is
 * the first addition past that starting set.
 */
final class Argument
{
    private function __construct() {}

    public static function any(): Matcher
    {
        return new AnyMatcher;
    }

    /**
     * @param  class-string  $type
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
}
