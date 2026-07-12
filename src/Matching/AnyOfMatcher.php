<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * Backs Argument::any($a, $b, ...) — matches if any one of the given
 * alternatives matches. Each alternative follows the same rule as
 * with()/not()/contains(): a bare literal is wrapped in EqualsMatcher,
 * anything already a Matcher is used as-is — so any(type('int'), type('float'))
 * composes just like any other combinator here, unlike Mockery's own
 * anyOf(), which only ever compares by strict identity against fixed
 * literal values and can't take a nested matcher at all.
 *
 * AnyMatcher (the true, unconstrained any()) stays a separate class rather
 * than folding into this one as "zero alternatives": that's a distinct,
 * far more common case (matches literally everything, including null) that
 * doesn't need an alternatives list walked on every call, and keeping it
 * as its own trivial class means Argument::any() with no arguments is
 * exactly as cheap as it always was.
 */
final class AnyOfMatcher implements Matcher
{
    /** @var list<Matcher> */
    private readonly array $alternatives;

    /**
     * @param  list<mixed>  $alternatives
     */
    public function __construct(array $alternatives)
    {
        $this->alternatives = array_map(
            static fn (mixed $alternative): Matcher => $alternative instanceof Matcher ? $alternative : new EqualsMatcher($alternative),
            $alternatives,
        );
    }

    public function matches(mixed $actual): bool
    {
        foreach ($this->alternatives as $alternative) {
            if ($alternative->matches($actual)) {
                return true;
            }
        }

        return false;
    }

    public function describe(): string
    {
        return sprintf(
            'any(%s)',
            implode(', ', array_map(static fn (Matcher $matcher): string => $matcher->describe(), $this->alternatives)),
        );
    }

    public function explainMismatch(mixed $actual): ?string
    {
        if ($this->matches($actual)) {
            return null;
        }

        return sprintf('expected %s, got %s', $this->describe(), ValueFormatter::describe($actual));
    }
}
