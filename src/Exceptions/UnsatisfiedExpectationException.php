<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown by $double->verify() when one or more expects() expectations
 * were never satisfied by the end of the test. Each UnsatisfiedExpectation
 * is paired with every other call actually observed for that method, so a
 * typo or wrong value in the actual call shows up as a plain fact rather
 * than leaving the person guessing.
 */
class UnsatisfiedExpectationException extends TestDoubleException
{
    use UnsatisfiedExpectationFields;
}
