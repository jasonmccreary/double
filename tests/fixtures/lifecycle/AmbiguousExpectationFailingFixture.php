<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures\Lifecycle;

use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Run in an isolated PHPUnit process by
 * VerifiesDoublesLifecycleTest — not part of the main suite (filename
 * deliberately doesn't end in "Test.php").
 *
 * Repeated identical expectations are consumed last-to-first, so the first
 * call gets the second registration's value and this assertion fails.
 */
final class AmbiguousExpectationFailingFixture extends TestCase
{
    use VerifiesDoubles;

    public function test_it_fails_because_repeated_expectations_return_in_reverse(): void
    {
        $repository = Double::for(BookRepositoryInterface::class);

        $repository->expects('count')->returns(1);
        $repository->expects('count')->returns(2);

        $this->assertSame(1, $repository->count());
        $this->assertSame(2, $repository->count());
    }
}
