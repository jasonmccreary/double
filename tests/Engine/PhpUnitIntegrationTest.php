<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\PhpUnitIntegration;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/**
 * phpunit/phpunit is always present while this suite itself runs, so the
 * class_exists(Assert::class) branch is always true here — this proves
 * registerPass() actually reaches Assert::assertTrue(true) rather than
 * merely assuming it does. The "PHPUnit unavailable" branch (a no-op) is
 * proven separately in CI — see tests/smoke/without-phpunit.php.
 */
final class PhpUnitIntegrationTest extends TestCase
{
    public function test_register_pass_increments_phpunits_process_global_assertion_count(): void
    {
        $before = Assert::getCount();

        PhpUnitIntegration::registerPass();

        $this->assertSame($before + 1, Assert::getCount());
    }
}
