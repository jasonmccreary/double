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
        parent::__construct($this->render());
    }

    private function render(): string
    {
        $blocks = array_map($this->renderOne(...), $this->expectations);

        $message = sprintf(
            "%d expectation(s) were not satisfied on test double \"%s\":\n\n%s",
            count($this->expectations),
            $this->label,
            implode("\n\n", $blocks),
        );

        if ($this->fabricated) {
            $message .= "\n\n".trim($this->fabricatedNote(true));
        }

        return $message;
    }

    private function renderOne(UnsatisfiedExpectation $expectation): string
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
