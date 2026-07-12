<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * Argument::contains($needle) — matches an iterable argument (array or
 * Traversable) that has at least one element satisfying $needle. One verb
 * covering what Mockery splits across contains()/hasKey()/hasValue()/
 * subset(), by taking $needle in one of three forms rather than adding a
 * separate verb per form:
 *
 * - A Matcher: true if any element matches it, e.g.
 *   contains(Argument::type('int')) — "at least one int in here somewhere."
 * - A plain callable: invoked as ($value, $key) per element, true the
 *   first time it returns true — the escape hatch for anything
 *   value-and-key shaped (mirrors Laravel Collection::contains()'s
 *   callback form, and underscore/lodash's (value, key) callback
 *   convention), so "has this key with this value" or "has a key matching
 *   this pattern" is one predicate away without a dedicated hasKey() verb.
 * - Anything else: a bare literal, wrapped in EqualsMatcher exactly like a
 *   bare literal passed to with() itself — "contains this value somewhere,"
 *   the plain-value case that motivated the verb.
 *
 * A Matcher is checked first, ahead of is_callable(), on purpose: nothing
 * in this codebase makes a Matcher itself invokable (no __invoke()), so
 * the two checks never actually collide today, but checking the more
 * specific, intentional type first is the correct order regardless of
 * whether that happens to matter yet.
 */
final class ContainsMatcher implements Matcher
{
    private readonly ?Matcher $valueMatcher;

    /** @var (callable(mixed, int|string): bool)|null */
    private $predicate;

    public function __construct(mixed $needle)
    {
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
