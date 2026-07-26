<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 */
final class DidYouMean
{
    private function __construct() {}

    /**
     * @param  list<string>  $candidates
     */
    public static function suggest(string $needle, array $candidates): ?string
    {
        $best = null;
        $bestDistance = null;

        foreach (array_unique($candidates) as $candidate) {
            $distance = levenshtein($needle, $candidate);
            // Threshold scales with the longer string (mirrors Symfony Console's
            // suggestion heuristic) instead of a flat cap — a flat cap either
            // over-matches short names or under-matches long ones.
            $threshold = (int) max(1, floor(max(strlen($needle), strlen($candidate)) / 3));

            if ($distance > $threshold) {
                continue;
            }

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        // Null rather than a wrong guess — a bad suggestion is worse than none.
        return $best;
    }
}
