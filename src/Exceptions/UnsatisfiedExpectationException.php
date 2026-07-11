<?php

declare(strict_types=1);

namespace JMac\Testing\Exceptions;

/**
 * Thrown by $double->verify() when one or more expects() expectations
 * were never satisfied by the end of the test. Each UnsatisfiedExpectation
 * is paired with every other call actually observed for that method — see
 * ARCHITECTURE.md, "Correlating unsatisfied expectations with actual
 * observed calls." Properties, constructor, and message rendering live in
 * UnsatisfiedExpectationFields, shared with
 * Integrations\PHPUnit\PHPUnitUnsatisfiedExpectationException — see that
 * trait's docblock and ARCHITECTURE.md's "PHPUnit integration".
 */
class UnsatisfiedExpectationException extends TestDoubleException
{
    use UnsatisfiedExpectationFields;
}
