<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

use JMac\Testing\Diagnostics\ArgumentComparison;

/**
 * @internal
 *
 * Attaches a real parameter name to each of MethodExpectation::compareArguments()'s
 * positional entries — Engine (MethodExpectation) stays ignorant of
 * reflection, so this is the one place that has both a positional
 * comparison and DoubleState::parameterNames() in hand. Shared by
 * Double::verifyState() (a never-satisfied expectation) and
 * ProxyBehavior::handleUnmatchedCall() (strict mode's immediate rejection)
 * — the two places that turn a 'comparisons' result into the labeled block
 * CallListFormatter::renderComparisonBlock() renders.
 */
final class ArgumentLabeler
{
    /**
     * @param  list<string>  $parameterNames
     * @param  list<array{index: int, differs: bool, text: string}>  $comparisons
     * @return list<ArgumentComparison>
     */
    public static function label(array $parameterNames, array $comparisons): array
    {
        return array_map(
            static fn (array $comparison): ArgumentComparison => new ArgumentComparison(
                label: self::labelFor($parameterNames, $comparison['index']),
                differs: $comparison['differs'],
                text: $comparison['text'],
            ),
            $comparisons,
        );
    }

    /**
     * $parameterNames only has one name per *declared* parameter, but a
     * variadic trailing one (or Argument::remaining()'s unconstrained tail)
     * can cover several actual argument positions — those past the last
     * declared name fall back to `name[n]` rather than reusing the same
     * bare name for every one of them.
     *
     * @param  list<string>  $parameterNames
     */
    private static function labelFor(array $parameterNames, int $index): string
    {
        if ($parameterNames === []) {
            return sprintf('argument %d', $index + 1);
        }

        $lastDeclaredIndex = count($parameterNames) - 1;

        return $index <= $lastDeclaredIndex
            ? $parameterNames[$index]
            : sprintf('%s[%d]', $parameterNames[$lastDeclaredIndex], $index - $lastDeclaredIndex);
    }
}
