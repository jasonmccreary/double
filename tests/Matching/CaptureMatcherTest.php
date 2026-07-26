<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\CaptureMatcher;
use JMac\Testing\Matching\Matcher;
use PHPUnit\Framework\TestCase;

final class CaptureMatcherTest extends TestCase
{
    public function test_matches_any_value_without_writing_the_reference(): void
    {
        $captured = null;
        $matcher = new CaptureMatcher($captured);

        $this->assertInstanceOf(Matcher::class, $matcher);
        $this->assertTrue($matcher->matches('anything'));
        $this->assertNull($captured);
    }

    public function test_capture_writes_the_value_into_the_referenced_variable(): void
    {
        $captured = null;
        $matcher = new CaptureMatcher($captured);

        $matcher->capture('hello');

        $this->assertSame('hello', $captured);
    }

    public function test_capture_overwrites_keeping_only_the_most_recent_value(): void
    {
        $captured = null;
        $matcher = new CaptureMatcher($captured);

        $matcher->capture('first');
        $matcher->capture('second');

        $this->assertSame('second', $captured);
    }
}
