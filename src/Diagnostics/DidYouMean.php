<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * @internal
 *
 * Suggests the closest match to an unrecognized name, for diagnostics like
 * UnknownMethodException — the same "explain what to do about it" standard
 * every other message in this library is held to.
 *
 * The distance threshold scales with the longer of the two strings
 * (Levenshtein distance <= length/3, mirroring Symfony Console's own
 * suggestion heuristic) rather than a flat cap: a flat threshold either
 * over-matches short names (e.g. "id" is only 2 edits from a dozen
 * unrelated 4-letter methods) or under-matches long ones. Returns null
 * rather than a wrong guess when nothing is close enough — a bad
 * suggestion is worse than none.
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
            $threshold = (int) max(1, floor(max(strlen($needle), strlen($candidate)) / 3));

            if ($distance > $threshold) {
                continue;
            }

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
