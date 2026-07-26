<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\AnyMatcher;
use PHPUnit\Framework\TestCase;

final class AnyMatcherTest extends TestCase
{
    public function test_matches_any_value_including_null(): void
    {
        $matcher = new AnyMatcher;

        $this->assertTrue($matcher->matches(1));
        $this->assertTrue($matcher->matches('anything'));
        $this->assertTrue($matcher->matches(null));
        $this->assertTrue($matcher->matches(new \stdClass));
    }

    public function test_never_produces_a_mismatch_explanation(): void
    {
        $this->assertNull((new AnyMatcher)->explainMismatch('anything'));
    }
}
