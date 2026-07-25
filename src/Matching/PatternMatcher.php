<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

use JMac\Testing\Diagnostics\ValueFormatter;

/**
 * Argument::matches($pattern) — matches a string argument against a PCRE
 * pattern, $pattern already including its own delimiters (e.g. '/^\d+$/'),
 * same convention as preg_match() itself.
 */
final class PatternMatcher implements Matcher
{
    public function __construct(
        private readonly string $pattern,
    ) {
        if (@preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException(sprintf(
                '`Argument::matches(%s)` is not a valid regular expression.',
                var_export($pattern, true),
            ));
        }
    }

    public function matches(mixed $actual): bool
    {
        // Requires string|Stringable rather than casting $actual to string —
        // an uncontrolled (string) cast on an array raises a PHP warning and
        // silently matches against the literal string "Array".
        if (! is_string($actual) && ! $actual instanceof \Stringable) {
            return false;
        }

        return preg_match($this->pattern, (string) $actual) === 1;
    }

    public function describe(): string
    {
        return sprintf('pattern(%s)', $this->pattern);
    }

    public function explainMismatch(mixed $actual): ?string
    {
        if ($this->matches($actual)) {
            return null;
        }

        return sprintf(
            'expected to match pattern %s, got %s',
            $this->pattern,
            ValueFormatter::describe($actual),
        );
    }
}
