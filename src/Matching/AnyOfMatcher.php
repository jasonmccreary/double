<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * Each alternative follows the same bare-literal-or-Matcher rule as
 * with()/not()/contains(), so a matcher can appear alongside plain values
 * instead of every alternative being limited to an exact-value comparison.
 * AnyMatcher covers the zero-alternatives case separately, since that's the
 * far more common path and needs no alternatives list to walk.
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
