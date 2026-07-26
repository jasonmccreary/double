<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown by unused() (see TestDouble::unused()) when the double
 * recorded at least one call to any method — unlike a received() assertion,
 * which checks one named method, this checks the double as a whole, so it's
 * a single immediate check rather than a fluent chain resolved later.
 */
class UnusedAssertionException extends TestDoubleException
{
    use UnusedAssertionFields;
}
