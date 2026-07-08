<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Diagnostics;

use PHPUnit\Framework\TestCase;

/**
 * Base for tests that compare rendered diagnostic output against a fixture
 * file rather than an inline string, so a rendering change shows up as a
 * reviewable diff on the fixture instead of a wall of escaped text in the
 * test itself. Run with UPDATE_GOLDEN=1 to (re)write every fixture a test
 * touches from the current actual output, then diff the fixture change
 * before committing it.
 */
abstract class GoldenFileTestCase extends TestCase
{
    protected function assertMatchesGolden(string $name, string $actual): void
    {
        $path = __DIR__.'/__fixtures__/'.$name.'.txt';

        if (getenv('UPDATE_GOLDEN') !== false) {
            file_put_contents($path, $actual);
            $this->markTestSkipped(sprintf('Golden file "%s" was (re)written from actual output.', $name));
        }

        $this->assertFileExists($path, sprintf(
            'Golden file "%s" does not exist. Run with UPDATE_GOLDEN=1 to create it, then review it.',
            $name,
        ));

        $this->assertSame(
            file_get_contents($path),
            $actual,
            sprintf(
                'Rendered output does not match golden file "%s". '
                .'Run with UPDATE_GOLDEN=1 to update it, then review the diff before committing.',
                $name,
            ),
        );
    }
}
