<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown the moment an inOrder()-marked expectation is called after a
 * later-declared inOrder()-marked expectation has already been called. An
 * orthogonal check, not a matching change: it only ever runs against
 * whichever expectation the existing last-registered-that-matches-wins
 * algorithm already selected. Properties, constructor, and message rendering
 * live in OutOfOrderCallFields, shared with
 * Integrations\PHPUnit\PHPUnitOutOfOrderCallException — see that trait's
 * docblock.
 */
class OutOfOrderCallException extends TestDoubleException
{
    use OutOfOrderCallFields;
}
