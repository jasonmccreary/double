<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Matching\EqualsMatcher;
use JMac\Testing\Matching\Matcher;

/**
 * One configured expects()/allows() entry. See ARCHITECTURE.md's "Verb
 * lineage" and "Sensible defaults" sections for the semantics each fluent
 * modifier must have.
 *
 * Argument matching is the Matcher contract (JMac\Testing\Matching, M2). A
 * bare literal passed to with() is wrapped in EqualsMatcher at this
 * boundary so the rest of the engine only ever deals in Matcher instances.
 */
final class MethodExpectation
{
    private const UNBOUNDED = PHP_INT_MAX;

    /** @var list<Matcher>|null */
    private ?array $argumentConstraints = null;

    /** @var list<mixed> */
    private array $returnValues = [];

    private bool $hasReturnConfigured = false;

    private ?\Throwable $throwable = null;

    /** @var (callable(mixed...): mixed)|null */
    private $returnsUsing = null;

    private int $minimumCalls;

    private int $maximumCalls;

    private int $timesMatched = 0;

    public function __construct(
        private readonly string $method,
        bool $required,
    ) {
        if ($required) {
            $this->minimumCalls = 1;
            $this->maximumCalls = 1;
        } else {
            $this->minimumCalls = 0;
            $this->maximumCalls = self::UNBOUNDED;
        }
    }

    public function method(): string
    {
        return $this->method;
    }

    public function with(mixed ...$arguments): static
    {
        $this->argumentConstraints = array_map(
            static fn (mixed $argument): Matcher => $argument instanceof Matcher ? $argument : new EqualsMatcher($argument),
            $arguments,
        );

        return $this;
    }

    public function returns(mixed ...$values): static
    {
        if ($values === []) {
            throw new \InvalidArgumentException('returns() requires at least one value.');
        }

        $this->returnValues = $values;
        $this->hasReturnConfigured = true;
        $this->throwable = null;
        $this->returnsUsing = null;

        return $this;
    }

    public function throws(\Throwable $exception): static
    {
        $this->throwable = $exception;
        $this->hasReturnConfigured = true;
        $this->returnValues = [];
        $this->returnsUsing = null;

        return $this;
    }

    /**
     * @param  callable(mixed...): mixed  $resolver
     */
    public function returnsUsing(callable $resolver): static
    {
        $this->returnsUsing = $resolver;
        $this->hasReturnConfigured = true;
        $this->returnValues = [];
        $this->throwable = null;

        return $this;
    }

    public function once(): static
    {
        return $this->times(1);
    }

    public function twice(): static
    {
        return $this->times(2);
    }

    public function times(int $count): static
    {
        $this->minimumCalls = $count;
        $this->maximumCalls = $count;

        return $this;
    }

    public function atLeastOnce(): static
    {
        $this->minimumCalls = 1;
        $this->maximumCalls = self::UNBOUNDED;

        return $this;
    }

    public function never(): static
    {
        $this->minimumCalls = 0;
        $this->maximumCalls = 0;

        return $this;
    }

    public function matchesArguments(array $arguments): bool
    {
        if ($this->argumentConstraints === null) {
            return true;
        }

        if (count($this->argumentConstraints) !== count($arguments)) {
            return false;
        }

        foreach ($this->argumentConstraints as $index => $matcher) {
            if (! $matcher->matches($arguments[$index])) {
                return false;
            }
        }

        return true;
    }

    public function recordMatch(): void
    {
        $this->timesMatched++;
    }

    public function timesMatched(): int
    {
        return $this->timesMatched;
    }

    public function minimumCalls(): int
    {
        return $this->minimumCalls;
    }

    public function maximumCalls(): int
    {
        return $this->maximumCalls;
    }

    public function isSatisfied(): bool
    {
        return $this->timesMatched >= $this->minimumCalls;
    }

    public function exceedsMaximum(): bool
    {
        return $this->timesMatched > $this->maximumCalls;
    }

    public function hasReturnConfigured(): bool
    {
        return $this->hasReturnConfigured;
    }

    public function resolveReturn(array $arguments): mixed
    {
        if ($this->throwable !== null) {
            throw $this->throwable;
        }

        if ($this->returnsUsing !== null) {
            return ($this->returnsUsing)(...$arguments);
        }

        $index = min($this->timesMatched - 1, count($this->returnValues) - 1);

        return $this->returnValues[$index];
    }

    public function describe(): string
    {
        $arguments = $this->argumentConstraints === null
            ? 'any arguments'
            : implode(', ', array_map(static fn (Matcher $matcher): string => $matcher->describe(), $this->argumentConstraints));

        return sprintf(
            '%s(%s) — expected %s, called %d time(s)',
            $this->method,
            $arguments,
            $this->describeExpectedCount(),
            $this->timesMatched,
        );
    }

    private function describeExpectedCount(): string
    {
        if ($this->minimumCalls === $this->maximumCalls) {
            return sprintf('exactly %d time(s)', $this->minimumCalls);
        }

        if ($this->maximumCalls === self::UNBOUNDED) {
            return sprintf('at least %d time(s)', $this->minimumCalls);
        }

        return sprintf('between %d and %d time(s)', $this->minimumCalls, $this->maximumCalls);
    }
}
