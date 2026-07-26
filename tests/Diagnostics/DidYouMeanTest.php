<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Diagnostics;

use JMac\Testing\Diagnostics\DidYouMean;
use PHPUnit\Framework\TestCase;

final class DidYouMeanTest extends TestCase
{
    public function test_suggests_a_single_character_typo(): void
    {
        $this->assertSame('save', DidYouMean::suggest('sav', ['find', 'save', 'delete', 'count']));
    }

    public function test_suggests_the_closest_of_several_candidates(): void
    {
        $this->assertSame('delete', DidYouMean::suggest('dilete', ['find', 'save', 'delete', 'count']));
    }

    public function test_returns_null_when_nothing_is_close_enough(): void
    {
        $this->assertNull(DidYouMean::suggest('bogus', ['find', 'save', 'delete', 'count']));
    }

    public function test_returns_null_for_an_empty_candidate_list(): void
    {
        $this->assertNull(DidYouMean::suggest('save', []));
    }

    public function test_ignores_duplicate_candidates(): void
    {
        $this->assertSame('save', DidYouMean::suggest('sav', ['save', 'save']));
    }
}
