<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Diagnostics;

use JMac\Testing\Diagnostics\Pluralizer;
use PHPUnit\Framework\TestCase;

final class PluralizerTest extends TestCase
{
    public function test_singular_count_uses_the_singular_word(): void
    {
        $this->assertSame('1 time', Pluralizer::pluralize(1, 'time', 'times'));
    }

    public function test_zero_count_uses_the_plural_word(): void
    {
        $this->assertSame('0 times', Pluralizer::pluralize(0, 'time', 'times'));
    }

    public function test_count_greater_than_one_uses_the_plural_word(): void
    {
        $this->assertSame('3 expectations', Pluralizer::pluralize(3, 'expectation', 'expectations'));
    }
}
