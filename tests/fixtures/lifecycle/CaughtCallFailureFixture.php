<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures\Lifecycle;

use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Run in an isolated PHPUnit process by VerifiesDoublesLifecycleTest, not
 * the main suite (the filename deliberately doesn't end in "Test.php").
 * The test body catches a call failure and passes; teardown must still fail.
 */
final class CaughtCallFailureFixture extends TestCase
{
    use VerifiesDoubles;

    public function test_it_catches_a_forbidden_call(): void
    {
        $repository = Double::for(BookRepositoryInterface::class);
        $repository->expects('delete')->never();

        try {
            $repository->delete(1);
        } catch (\Throwable) {
        }

        $this->assertTrue(true);
    }
}
