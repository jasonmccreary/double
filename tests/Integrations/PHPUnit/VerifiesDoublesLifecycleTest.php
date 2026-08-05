<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Integrations\PHPUnit;

use PHPUnit\Framework\TestCase;

/**
 * VerifiesDoubles's #[Before]/#[After] hooks only run for real inside an
 * actual PHPUnit lifecycle (PHPUnit's own runBare() ordering, see
 * VerifiesDoubles's docblock) — unit-testing Double::armAutoVerify()/
 * verifyAll() directly (as DoubleTest does) can't reproduce a bug that's
 * specifically about PHPUnit's hook sequencing. So these fixtures are run in
 * an isolated `vendor/bin/phpunit` process and the real output is asserted
 * on, the same way the bug was originally confirmed against a downstream
 * project's suite.
 *
 * Fixtures live in tests/fixtures/lifecycle/ and deliberately don't match
 * phpunit.xml's default "*Test.php" discovery suffix, so the main suite
 * never runs them directly.
 */
final class VerifiesDoublesLifecycleTest extends TestCase
{
    public function test_a_failing_assertion_before_the_double_is_called_reports_only_the_real_failure(): void
    {
        $output = $this->runFixture('FailsAssertionBeforeExpectationFixture.php');

        $this->assertSame(1, $this->failureCount($output), "Expected exactly 1 failure:\n{$output}");
        $this->assertStringContainsString('Failed asserting that two strings are identical.', $output);
        $this->assertStringNotContainsString('expected `delete(1)` to be called', $output);
    }

    public function test_a_genuinely_unmet_expectation_still_fails_when_the_test_body_passes(): void
    {
        $output = $this->runFixture('UnmetExpectationOnlyFixture.php');

        $this->assertSame(1, $this->failureCount($output), "Expected exactly 1 failure:\n{$output}");
        $this->assertStringContainsString('expected `delete(1)` to be called exactly 1 time, but it was never called', $output);
    }

    private function runFixture(string $filename): string
    {
        $root = \dirname(__DIR__, 3);

        $command = implode(' ', array_map(escapeshellarg(...), [
            $root.'/vendor/bin/phpunit',
            '--no-configuration',
            '--bootstrap', $root.'/vendor/autoload.php',
            $root.'/tests/fixtures/lifecycle/'.$filename,
        ]));

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        \assert(\is_resource($process));

        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output;
    }

    private function failureCount(string $output): int
    {
        if (preg_match('/Failures: (\d+)/', $output, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }
}
