<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * The presentation-ready, labeled counterpart to one entry from
 * MethodExpectation::compareArguments()'s positional 'comparisons' list —
 * built by Double::verifyState() zipping that list against
 * DoubleState::parameterNames(), so Engine stays ignorant of reflection and
 * Diagnostics stays ignorant of matching.
 */
final class ArgumentComparison
{
    public function __construct(
        public readonly string $label,
        public readonly bool $differs,
        public readonly string $text,
    ) {}
}
