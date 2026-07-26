<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

interface NullableParamInterface
{
    public function greet(?string $name = null): string;

    /**
     * No type hint at all; defaults to null. No PHP 8.4 deprecation
     * applies here since there's no type declaration to be implicitly
     * nullable — used to prove ClassGenerator doesn't add a spurious "?"
     * for genuinely untyped parameters.
     */
    public function untypedDefaultNull($value = null): mixed;
}
