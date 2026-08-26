<?php

declare(strict_types=1);

namespace JMac\Testing;

use JMac\Testing\Diagnostics\Diagnostic;

/**
 * One expects()/allows()/received()/unused() check resolving, pass or fail —
 * delivered to every listener registered via `Double::listen()`, at the
 * moment the check itself resolves rather than batched at verify time.
 *
 * $method is null for a whole-double check: unused(), and the verify-time
 * unmet-expectation check (which can bundle several unmet expectations
 * across different methods into a single failure already). It's set for the
 * four immediate call-time failures (an unmatched call on a Strict double, a
 * times()/never() limit exceeded, an out-of-order call, an argument
 * mismatch against a required expectation) and for received().
 *
 * $failure is the real exception the check would otherwise have thrown —
 * the same PHPUnit-aware Diagnostic&\Throwable ExceptionFactory already
 * builds, not a re-derived description. Its public fields are already
 * frozen, semver-guaranteed API, so this carries full diagnostic detail
 * (argument comparisons, observed calls, ...) without inventing a second,
 * lossier format to keep in sync. Null when $passed is true.
 */
final class CheckEvent
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $method,
        public readonly bool $passed,
        public readonly (Diagnostic&\Throwable)|null $failure,
    ) {}
}
