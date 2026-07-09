<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

use JMac\Testing\Diagnostics\UnsatisfiedExpectation;

/**
 * Thrown by TestDouble::verify() when one or more expects() expectations
 * were never satisfied by the end of the test. Each UnsatisfiedExpectation
 * is paired with every other call actually observed for that method — see
 * ARCHITECTURE.md, "Correlating unsatisfied expectations with actual
 * observed calls."
 */
class UnsatisfiedExpectationException extends TestDoubleException
{
    /**
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public function __construct(
        public readonly string $label,
        public readonly array $expectations,
        public readonly bool $fabricated = false,
    ) {
        parent::__construct(self::renderMessage($label, $expectations, $fabricated));
    }

    /**
     * Static (not just private) so PHPUnitUnsatisfiedExpectationException —
     * which cannot extend this class, see ARCHITECTURE.md's "PHPUnit
     * integration" — renders byte-identical prose without duplicating this
     * logic.
     *
     * @param  list<UnsatisfiedExpectation>  $expectations
     */
    public static function renderMessage(string $label, array $expectations, bool $fabricated): string
    {
        $blocks = array_map(self::renderOne(...), $expectations);

        $message = sprintf(
            "%d expectation(s) were not satisfied on test double \"%s\":\n\n%s",
            count($expectations),
            $label,
            implode("\n\n", $blocks),
        );

        if ($fabricated) {
            $message .= "\n\n".trim(self::fabricatedNote(true));
        }

        return $message;
    }

    private static function renderOne(UnsatisfiedExpectation $expectation): string
    {
        $lines = ['    '.$expectation->description];

        if ($expectation->otherObservedCalls !== []) {
            $lines[] = '';
            $lines[] = sprintf(
                '    "%s" was called with different arguments elsewhere in this test:',
                $expectation->method,
            );
            $lines[] = '';

            foreach ($expectation->otherObservedCalls as $call) {
                $lines[] = sprintf('        %s(%s)', $expectation->method, $call);
            }
        }

        return implode("\n", $lines);
    }
}
