<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures\Lifecycle;

use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\TestDouble;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Run in an isolated PHPUnit process by
 * VerifiesDoublesLifecycleTest — not part of the main suite (filename
 * deliberately doesn't end in "Test.php", which is what the main
 * phpunit.xml uses to discover tests under ./tests).
 *
 * Reproduces CORRECTNESS.md's repro verbatim: an expects() is registered,
 * then a plain assertion fails before the double is ever called. Without
 * the fix, this reports 2 failures instead of 1.
 */
final class FailsAssertionBeforeExpectationFixture extends TestCase
{
    use VerifiesDoubles;

    public function test_it_fails_a_plain_assertion_before_the_double_is_called(): void
    {
        $repository = TestDouble::for(BookRepositoryInterface::class);

        $repository->expects('delete')->with(1);

        $this->assertSame('wrong', 'actual');
    }
}
