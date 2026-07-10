<?php

declare(strict_types=1);

namespace JMac\Testing\Matching;

/**
 * Argument::satisfies($predicate) — matches any value for which the given
 * callable returns a truthy result. See ARCHITECTURE.md's example:
 * `Argument::satisfies(fn ($id) => $id > 100)`.
 */
final class PredicateMatcher implements Matcher
{
    /** @var callable(mixed): bool */
    private $predicate;

    public function __construct(callable $predicate)
    {
        $this->predicate = $predicate;
    }

    public function matches(mixed $actual): bool
    {
        return (bool) ($this->predicate)($actual);
    }

    public function describe(): string
    {
        return 'satisfies(...)';
    }

    public function explainMismatch(mixed $actual): ?string
    {
        if ($this->matches($actual)) {
            return null;
        }

        return sprintf('value did not satisfy predicate: %s', ValueFormatter::describe($actual));
    }
}
