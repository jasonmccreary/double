<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\PredicateMatcher;
use PHPUnit\Framework\TestCase;

final class PredicateMatcherTest extends TestCase
{
    public function test_matches_when_the_predicate_returns_true(): void
    {
        $matcher = new PredicateMatcher(fn (int $id): bool => $id > 100);

        $this->assertTrue($matcher->matches(101));
        $this->assertFalse($matcher->matches(100));
    }

    public function test_explain_mismatch_is_null_when_it_matches(): void
    {
        $matcher = new PredicateMatcher(fn (int $id): bool => $id > 100);

        $this->assertNull($matcher->explainMismatch(101));
    }

    public function test_explain_mismatch_describes_the_actual_value_when_it_does_not_match(): void
    {
        $matcher = new PredicateMatcher(fn (int $id): bool => $id > 100);

        $this->assertSame('value did not satisfy predicate: 5', $matcher->explainMismatch(5));
    }
}
