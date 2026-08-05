<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures\Lifecycle;

use JMac\Testing\Double;
use JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Run in an isolated PHPUnit process by VerifiesDoublesLifecycleTest —
 * control case. The test body itself passes cleanly, but the registered
 * expects() is genuinely never called. Must still fail (with the
 * unsatisfied-expectation message) — proves the fix doesn't swallow real
 * unmet expectations on the success path.
 */
final class UnmetExpectationOnlyFixture extends TestCase
{
    use VerifiesDoubles;

    public function test_it_never_calls_the_expected_method(): void
    {
        $repository = Double::for(BookRepositoryInterface::class);

        $repository->expects('delete')->with(1);

        $this->assertTrue(true);
    }
}
