<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown the moment an ordered()-marked expectation is called after a
 * later-declared ordered()-marked expectation has already been called. An
 * orthogonal check, not a matching change: it only ever runs against
 * whichever expectation ProxyBehavior::findMatch() already selected.
 */
class OutOfOrderCallException extends DoubleException
{
    use OutOfOrderCallFields;
}
