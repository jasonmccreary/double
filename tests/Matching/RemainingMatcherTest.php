<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\RemainingMatcher;
use PHPUnit\Framework\TestCase;

final class RemainingMatcherTest extends TestCase
{
    public function test_matches_any_value(): void
    {
        $matcher = new RemainingMatcher;

        $this->assertTrue($matcher->matches('anything'));
        $this->assertTrue($matcher->matches(null));
        $this->assertTrue($matcher->matches(42));
    }

    public function test_describe_renders_as_an_ellipsis(): void
    {
        $this->assertSame('...', (new RemainingMatcher)->describe());
    }

    public function test_explain_mismatch_is_always_null(): void
    {
        $this->assertNull((new RemainingMatcher)->explainMismatch('anything'));
    }
}
