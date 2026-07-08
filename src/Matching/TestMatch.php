<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * The argument-matcher facade, named to echo TestDouble itself (see
 * ARCHITECTURE.md, "Verb lineage"). Starting matcher set only — any(),
 * type(), that() — deliberately minimal for v1; more get added once real
 * usage shows what's actually needed, rather than porting a full matcher
 * catalog speculatively.
 */
final class TestMatch
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
    public static function that(callable $predicate): Matcher
    {
        return new PredicateMatcher($predicate);
    }
}
