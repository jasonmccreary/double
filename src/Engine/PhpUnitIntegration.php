<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use PHPUnit\Framework\Assert;

/**
 * @internal
 *
 * PHPUnit's TestRunner flags a test "risky" ("did not perform any
 * assertions") by checking Assert::getCount() after the test runs — a
 * process-global static counter, not tied to any TestCase instance. Every
 * verification path in this library (verify(), unused(), received()->check())
 * throws on failure but returns silently on success, so PHPUnit never learns
 * a check ran. Calling registerPass() from those success paths registers a
 * real assertion on that counter, the same sanctioned integration point
 * third-party assertion libraries are meant to use.
 *
 * Same class_exists guard as ExceptionFactory::phpUnitIsAvailable(), so this
 * stays a no-op when PHPUnit isn't installed.
 */
final class PhpUnitIntegration
{
    public static function registerPass(): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue(true);
        }
    }
}
