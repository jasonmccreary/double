<?php

declare(strict_types=1);

namespace JMac\Testing\Diagnostics;

/**
 * A group of two or more expectations registered for the same method, with
 * identical argument matching, that each carry their own finite call limit —
 * the shape produced by repeating expects()/allows() to encode a sequence of
 * answers (a common Mockery idiom) instead of using one expectation's
 * times()->returns(...). Matching is most-recently-registered first (see
 * ProxyBehavior::findMatch()), so this shape returns values in the reverse
 * of registration order rather than in sequence, with nothing else in the
 * engine able to tell that apart from a genuine mistake.
 */
final class AmbiguousExpectation
{
    public function __construct(
        public readonly string $method,
        public readonly string $argumentsDescription,
        public readonly int $count,
    ) {}
}
