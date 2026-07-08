<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\TypeMatcher;
use JMac\Testing\Tests\Fixtures\Book;
use PHPUnit\Framework\TestCase;

final class TypeMatcherTest extends TestCase
{
    public function test_matches_instances_of_the_given_class(): void
    {
        $matcher = new TypeMatcher(Book::class);

        $this->assertTrue($matcher->matches(new Book('Some Title')));
        $this->assertFalse($matcher->matches(new \stdClass));
        $this->assertFalse($matcher->matches('Some Title'));
    }

    public function test_matches_instances_of_an_interface(): void
    {
        $matcher = new TypeMatcher(\Countable::class);

        $this->assertTrue($matcher->matches(new \ArrayObject([1, 2])));
        $this->assertFalse($matcher->matches(new \stdClass));
    }

    public function test_explain_mismatch_names_the_expected_type_and_actual_type(): void
    {
        $matcher = new TypeMatcher(Book::class);

        $this->assertSame(
            sprintf('expected an instance of %s, got stdClass', Book::class),
            $matcher->explainMismatch(new \stdClass),
        );
    }
}
