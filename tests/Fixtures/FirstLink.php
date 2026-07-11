<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Fixtures;

/**
 * A genuine non-cyclic, non-self-referential chain (FirstLink ->
 * SecondLink -> ThirdLink, three distinct types) for exercising
 * SafeDefaultResolver's fabrication limit — see FabricationLimitExceededException
 * and ARCHITECTURE.md's "Guardrails on fabrication". Unlike NodeInterface,
 * nothing here returns its own declaring type, so the limit — not the
 * self/static cycle-closing path — is what's under test.
 */
interface FirstLink
{
    public function toSecond(): SecondLink;
}
