<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * A plain value object with no __set_state() — standing in for
 * Illuminate\Http\Resources\MissingValue, the real-world class whose "new in
 * initializers" (PHP 8.1+) default value crashed ClassGenerator.
 */
final class NewInInitializerDefault
{
    public function __construct(public readonly string $label = 'missing') {}
}
