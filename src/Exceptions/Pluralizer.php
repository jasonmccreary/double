<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * @internal
 *
 * Shared plural-vs-singular word choice, so no message has to hand-roll its
 * own "$count === 1 ? ... : ..." (or, worse, a lazy "item(s)" that never
 * actually reads as either singular or plural). Lives in Exceptions, not
 * Engine, so Engine\MethodExpectation::describe() — whose rendered
 * "expected N time(s)" prose is what UnsatisfiedExpectationException
 * ultimately displays — can depend on it without breaking the acyclic
 * module rule in ARCHITECTURE.md's "Module boundaries": Engine already
 * depends on Exceptions, never the other way around.
 *
 * Returns the count and the word together (e.g. "1 time" / "3 times")
 * since every current call site wants both, not just the bare word.
 */
final class Pluralizer
{
    public static function pluralize(int $count, string $singular, string $plural): string
    {
        return sprintf('%d %s', $count, $count === 1 ? $singular : $plural);
    }
}
