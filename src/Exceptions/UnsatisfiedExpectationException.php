<?php

declare(strict_types=1);

namespace TestDouble\Exceptions;

/**
 * Thrown by TestDouble::verify() when one or more expects() expectations
 * were never satisfied by the end of the test.
 *
 * M1 scope note: ARCHITECTURE.md's "Correlating unsatisfied expectations
 * with actual observed calls" (pairing each unmet expectation with other
 * calls actually observed for that method) is explicitly M3 scope. This
 * exception only reports the plain unmet-count fact for now.
 */
final class UnsatisfiedExpectationException extends TestDoubleException
{
    /**
     * @param string[] $descriptions one pre-formatted line per unmet expectation
     */
    public static function forUnmet(string $label, array $descriptions): self
    {
        return new self(sprintf(
            "%d expectation(s) were not satisfied on test double \"%s\":\n\n%s",
            count($descriptions),
            $label,
            implode("\n", array_map(static fn (string $d): string => '    ' . $d, $descriptions)),
        ));
    }
}
