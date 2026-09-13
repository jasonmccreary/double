<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown by $double->verify() when two or more expectations were registered
 * for the same method, with identical argument matching, each carrying its
 * own finite call limit — the shape of repeating expects()/allows() to try
 * to encode a sequence of answers, which matches most-recently-registered
 * first instead of in registration order.
 */
class AmbiguousExpectationException extends DoubleException
{
    use AmbiguousExpectationFields;
}
