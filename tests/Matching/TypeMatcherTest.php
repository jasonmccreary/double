<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Matching;

use JMac\Testing\Matching\TypeMatcher;
use JMac\Testing\Tests\Support\Book;
use PHPUnit\Framework\Attributes\DataProvider;
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
            sprintf('expected type %s, got stdClass', Book::class),
            $matcher->explainMismatch(new \stdClass),
        );
    }

    public static function builtinTypeFixtures(): iterable
    {
        yield 'int' => ['int', 42, 'not an int'];
        yield 'float' => ['float', 4.2, 42];
        yield 'string' => ['string', 'hello', 42];
        yield 'bool' => ['bool', true, 42];
        yield 'array' => ['array', [1, 2], 42];
        yield 'object' => ['object', new \stdClass, 42];
        yield 'callable' => ['callable', 'strlen', 42];
        yield 'iterable' => ['iterable', [1, 2], 42];
        yield 'null' => ['null', null, 42];
    }

    #[DataProvider('builtinTypeFixtures')]
    public function test_matches_a_builtin_php_type_by_name(string $type, mixed $matching, mixed $nonMatching): void
    {
        $matcher = new TypeMatcher($type);

        $this->assertTrue($matcher->matches($matching));
        $this->assertFalse($matcher->matches($nonMatching));
    }

    public function test_builtin_type_name_matching_is_case_insensitive(): void
    {
        $matcher = new TypeMatcher('INT');

        $this->assertTrue($matcher->matches(42));
        $this->assertFalse($matcher->matches('42'));
    }

    public function test_mixed_matches_any_value(): void
    {
        $matcher = new TypeMatcher('mixed');

        $this->assertTrue($matcher->matches(42));
        $this->assertTrue($matcher->matches(null));
        $this->assertTrue($matcher->matches(new \stdClass));
    }

    public function test_describe_reads_the_same_for_a_builtin_type_as_for_a_class(): void
    {
        $this->assertSame('type(int)', (new TypeMatcher('int'))->describe());
        $this->assertSame(sprintf('type(%s)', Book::class), (new TypeMatcher(Book::class))->describe());
    }
}
