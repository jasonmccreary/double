<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * One verb, in three forms, rather than a separate verb per search shape.
 * The callback form follows a ($value, $key) convention, same as
 * Laravel's Collection::contains().
 */
final class ContainsMatcher implements Matcher
{
    private readonly ?Matcher $valueMatcher;

    /** @var (callable(mixed, int|string): bool)|null */
    private $predicate;

    public function __construct(mixed $needle)
    {
        // Matcher checked first, ahead of is_callable() — nothing in this codebase
        // makes a Matcher itself invokable, so the two never actually collide
        // today, but the more specific, intentional type should still win.
        if ($needle instanceof Matcher) {
            $this->valueMatcher = $needle;
            $this->predicate = null;
        } elseif (is_callable($needle)) {
            $this->valueMatcher = null;
            $this->predicate = $needle;
        } else {
            $this->valueMatcher = new EqualsMatcher($needle);
            $this->predicate = null;
        }
    }

    public function matches(mixed $actual): bool
    {
        if (! is_iterable($actual)) {
            return false;
        }

        foreach ($actual as $key => $value) {
            if ($this->predicate !== null ? ($this->predicate)($value, $key) : $this->valueMatcher->matches($value)) {
                return true;
            }
        }

        return false;
    }

    public function describe(): string
    {
        return sprintf('contains(%s)', $this->valueMatcher?->describe() ?? '...');
    }

    public function explainMismatch(mixed $actual): ?string
    {
        if ($this->matches($actual)) {
            return null;
        }

        if (! is_iterable($actual)) {
            return sprintf('expected an iterable to search, got %s', ValueFormatter::describe($actual));
        }

        return sprintf('expected %s, got %s', $this->describe(), ValueFormatter::describe($actual));
    }
}
