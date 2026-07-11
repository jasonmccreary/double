<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 *
 * Shared plural-vs-singular word choice, so no message has to hand-roll its
 * own "$count === 1 ? ... : ..." (or, worse, a lazy "item(s)" that never
 * actually reads as either singular or plural). Lives in Diagnostics, along
 * with ValueFormatter/ArgumentFormatter, as the shared home for rendering
 * logic every other module can reach — both Engine\MethodExpectation
 * (whose rendered "expected N time(s)" prose is what
 * UnsatisfiedExpectationException ultimately displays) and
 * Exceptions\UnsatisfiedExpectationFields depend on it without breaking the
 * acyclic module rule in ARCHITECTURE.md's "Module boundaries".
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
