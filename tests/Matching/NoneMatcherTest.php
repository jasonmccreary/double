<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\NoneMatcher;
use PHPUnit\Framework\TestCase;

final class NoneMatcherTest extends TestCase
{
    public function test_matches_any_value(): void
    {
        $matcher = new NoneMatcher;

        $this->assertTrue($matcher->matches('anything'));
        $this->assertTrue($matcher->matches(null));
        $this->assertTrue($matcher->matches(42));
    }

    public function test_describe_renders_as_no_arguments(): void
    {
        $this->assertSame('no arguments', (new NoneMatcher)->describe());
    }

    public function test_explain_mismatch_is_always_null(): void
    {
        $this->assertNull((new NoneMatcher)->explainMismatch('anything'));
    }
}
