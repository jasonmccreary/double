<?php

declare(strict_types=1);

/**
 * Proves the "absent" half of the optional-PHPUnit-integration promise: the
 * phpunit-11-compat/12-compat matrix in ci.yml proves this library works
 * across supported PHPUnit *versions*, but nothing previously proved it
 * works with PHPUnit not installed at all — Engine\ExceptionFactory's
 * class_exists() branching was only ever reasoned about for that case,
 * never executed against it.
 *
 * Run via `composer install --no-dev`, which drops phpunit/phpunit from
 * vendor/ entirely — and, as a side effect, disables autoload-dev, so
 * tests/Support's fixtures aren't autoloadable either. This script is
 * deliberately self-contained (its own inline fixture below) rather than
 * relying on that. Not a PHPUnit test itself, since PHPUnit isn't installed
 * when this runs — plain assertions, non-zero exit on any failure.
 */

require __DIR__.'/../../vendor/autoload.php';

use JMac\Testing\Exceptions\UnexpectedCallException;
use JMac\Testing\Exceptions\UnsatisfiedExpectationException;
use JMac\Testing\TestDouble;
use PHPUnit\Framework\TestCase;

interface SmokeTestRepository
{
    public function find(int $id): ?string;

    public function save(string $value): bool;
}

/** @var list<string> $failures */
$failures = [];

function check(string $description, callable $assertion): void
{
    global $failures;

    try {
        $assertion();
        echo "  ok   {$description}\n";
    } catch (Throwable $e) {
        $failures[] = sprintf('%s: %s', $description, $e->getMessage());
        echo "  FAIL {$description}: {$e->getMessage()}\n";
    }
}

check(
    'PHPUnit is genuinely absent, not just unrequired (a precondition for this script to prove anything)',
    static function (): void {
        if (class_exists(TestCase::class)) {
            throw new RuntimeException('PHPUnit is installed — run via `composer install --no-dev` instead.');
        }
    },
);

check(
    'creating and configuring a double works with no PHPUnit installed',
    static function (): void {
        $double = TestDouble::for(SmokeTestRepository::class);
        $double->allows('find')->with(123)->returns('Dune');

        if ($double->find(123) !== 'Dune') {
            throw new RuntimeException('configured call did not return the configured value.');
        }
    },
);

check(
    'an unmatched call in Strict mode throws the plain exception, never the PHPUnit sibling',
    static function (): void {
        $double = TestDouble::for(SmokeTestRepository::class)->strict();

        try {
            $double->find(1);
        } catch (UnexpectedCallException $e) {
            return;
        }

        throw new RuntimeException('expected UnexpectedCallException to be thrown.');
    },
);

check(
    'verify() on an unmet expectation throws the plain exception, never the PHPUnit sibling',
    static function (): void {
        $double = TestDouble::for(SmokeTestRepository::class);
        $double->expects('save')->with('Dune')->returns(true);

        try {
            $double->verify();
        } catch (UnsatisfiedExpectationException $e) {
            return;
        }

        throw new RuntimeException('expected UnsatisfiedExpectationException to be thrown.');
    },
);

if ($failures !== []) {
    fwrite(STDERR, sprintf("\n%d check(s) failed with PHPUnit genuinely absent.\n", count($failures)));
    exit(1);
}

echo "\nAll checks passed with PHPUnit genuinely absent.\n";
