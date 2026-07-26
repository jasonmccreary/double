<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Exceptions;

use PHPUnit\Framework\TestCase;

/**
 * Base for tests that compare a rendered exception message against a
 * fixture file rather than an inline string, so a rendering change shows up
 * as a reviewable diff on the fixture instead of a wall of escaped text in
 * the test itself. Fixtures are edited by hand, directly, rather than
 * regenerated from actual output — there is deliberately no write-mode
 * escape hatch here.
 */
abstract class GoldenFileTestCase extends TestCase
{
    protected function assertMatchesGolden(string $name, string $actual): void
    {
        $path = __DIR__.'/../fixtures/exceptions/'.$name.'.txt';

        $this->assertFileExists($path, sprintf('Golden file "%s" does not exist.', $name));

        $this->assertSame(
            file_get_contents($path),
            $actual,
            sprintf('Rendered output does not match golden file "%s".', $name),
        );
    }
}
