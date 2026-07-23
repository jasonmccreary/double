<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\ClassGenerator;
use JMac\Testing\Exceptions\InvalidDoubleTargetException;
use JMac\Testing\Exceptions\ReservedNameCollisionException;
use JMac\Testing\TestDouble;
use JMac\Testing\Tests\Support\AllowsCollisionInterface;
use JMac\Testing\Tests\Support\AuthorizerInterface;
use JMac\Testing\Tests\Support\Book;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use JMac\Testing\Tests\Support\ConcreteLogger;
use JMac\Testing\Tests\Support\EnumDefaultInterface;
use JMac\Testing\Tests\Support\ExpectsCollisionInterface;
use JMac\Testing\Tests\Support\Fillable;
use JMac\Testing\Tests\Support\FinalLogger;
use JMac\Testing\Tests\Support\HasStaticMethod;
use JMac\Testing\Tests\Support\NullableParamInterface;
use JMac\Testing\Tests\Support\PassthruCollisionInterface;
use JMac\Testing\Tests\Support\ReceivedCollisionInterface;
use JMac\Testing\Tests\Support\Sized;
use JMac\Testing\Tests\Support\StaticMethodInterface;
use JMac\Testing\Tests\Support\StrictCollisionInterface;
use JMac\Testing\Tests\Support\Suit;
use JMac\Testing\Tests\Support\UnionTypeInterface;
use JMac\Testing\Tests\Support\VariadicInterface;
use JMac\Testing\Tests\Support\VerifyCollisionInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClassGeneratorTest extends TestCase
{
    public function test_generates_a_class_implementing_a_target_interface(): void
    {
        $generated = (new ClassGenerator)->generate(BookRepositoryInterface::class);

        $this->assertTrue(class_exists($generated));
        $this->assertTrue(is_subclass_of($generated, BookRepositoryInterface::class));
    }

    public function test_generates_a_class_extending_a_target_class_without_invoking_its_constructor(): void
    {
        $generated = (new ClassGenerator)->generate(ConcreteLogger::class);

        $this->assertTrue(is_subclass_of($generated, ConcreteLogger::class));

        // Instantiating via the real (still-inherited) constructor would throw;
        // the generated class's own factory bypasses it entirely.
        $instance = $generated::__td_instantiate();

        $this->assertInstanceOf(ConcreteLogger::class, $instance);
    }

    public function test_each_call_to_generate_produces_a_distinct_class_for_the_same_target(): void
    {
        $generator = new ClassGenerator;

        $first = $generator->generate(BookRepositoryInterface::class);
        $second = $generator->generate(BookRepositoryInterface::class);

        $this->assertNotSame($first, $second);
        $this->assertTrue(class_exists($first));
        $this->assertTrue(class_exists($second));
    }

    public function test_rejects_a_non_existent_target(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage('no such class or interface exists');

        (new ClassGenerator)->generate('JMac\Testing\Tests\Support\NoSuchThing');
    }

    public function test_rejects_a_final_class(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage("it's final");

        (new ClassGenerator)->generate(FinalLogger::class);
    }

    /**
     * Regression check: an interface's methods are all implicitly abstract,
     * including static ones — before assertNoAbstractStaticMethods()
     * existed, this crashed with an uncatchable PHP fatal error out of the
     * eval()'d source ("must therefore be declared abstract or implement
     * the remaining methods") instead of the library's own exception.
     */
    public function test_rejects_a_target_with_an_abstract_static_method(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage('static method (`make`)');

        (new ClassGenerator)->generate(StaticMethodInterface::class);
    }

    /**
     * A static method on a concrete class already has an implementation to
     * fall back on — the generated class simply inherits it unoverridden
     * (see overridableMethods()'s docblock), so this is not the same
     * rejection as the abstract case above.
     */
    public function test_does_not_reject_a_concrete_class_with_a_static_method(): void
    {
        $generated = (new ClassGenerator)->generate(HasStaticMethod::class);

        $this->assertTrue(class_exists($generated));
    }

    public function test_generates_a_class_implementing_multiple_target_interfaces(): void
    {
        $generated = (new ClassGenerator)->generateForIntersection([Fillable::class, Sized::class]);

        $this->assertTrue(is_subclass_of($generated, Fillable::class));
        $this->assertTrue(is_subclass_of($generated, Sized::class));
    }

    public function test_intersection_rejects_a_non_existent_target(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage('no such class or interface exists');

        (new ClassGenerator)->generateForIntersection([Fillable::class, 'JMac\Testing\Tests\Support\NoSuchThing']);
    }

    public function test_intersection_rejects_a_concrete_class_among_the_targets(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage("it's a class");

        (new ClassGenerator)->generateForIntersection([Fillable::class, ConcreteLogger::class]);
    }

    public function test_intersection_rejects_the_same_target_passed_twice(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage('passed more than once');

        (new ClassGenerator)->generateForIntersection([Fillable::class, Fillable::class]);
    }

    public static function reservedNameFixtures(): iterable
    {
        yield 'expects' => [ExpectsCollisionInterface::class, 'expects'];
        yield 'allows' => [AllowsCollisionInterface::class, 'allows'];
        yield 'strict' => [StrictCollisionInterface::class, 'strict'];
        yield 'passthru' => [PassthruCollisionInterface::class, 'passthru'];
        yield 'received' => [ReceivedCollisionInterface::class, 'received'];
        yield 'verify' => [VerifyCollisionInterface::class, 'verify'];
        yield 'AuthorizerInterface allows()' => [AuthorizerInterface::class, 'allows'];
    }

    #[DataProvider('reservedNameFixtures')]
    public function test_rejects_a_target_declaring_a_reserved_control_method_name(string $target, string $method): void
    {
        try {
            (new ClassGenerator)->generate($target);
            $this->fail('Expected ReservedNameCollisionException to be thrown.');
        } catch (ReservedNameCollisionException $exception) {
            $this->assertStringContainsString($target, $exception->getMessage());
            $this->assertStringContainsString($method, $exception->getMessage());
        }
    }

    public function test_never_emits_an_implicit_nullable_parameter_signature(): void
    {
        $generated = (new ClassGenerator)->generate(NullableParamInterface::class);

        $parameter = (new \ReflectionMethod($generated, 'greet'))->getParameters()[0];

        $this->assertTrue($parameter->allowsNull());
        $this->assertSame('?string', (string) $parameter->getType());
    }

    public function test_generated_methods_with_explicit_nullable_parameters_trigger_no_deprecation(): void
    {
        $instance = TestDouble::for(NullableParamInterface::class);

        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, E_DEPRECATED);

        try {
            $instance->allows('greet')->returns('hi');
            $instance->greet();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }

    public function test_does_not_add_a_spurious_nullable_marker_to_a_genuinely_untyped_parameter(): void
    {
        $generated = (new ClassGenerator)->generate(NullableParamInterface::class);

        $parameter = (new \ReflectionMethod($generated, 'untypedDefaultNull'))->getParameters()[0];

        $this->assertNull($parameter->getType());
        $this->assertTrue($parameter->isDefaultValueAvailable());
        $this->assertNull($parameter->getDefaultValue());
    }

    public function test_supports_union_typed_parameters_and_returns(): void
    {
        $instance = TestDouble::for(UnionTypeInterface::class);

        $instance->allows('accept')->returns('ok');

        $this->assertSame('ok', $instance->accept(123));
    }

    public function test_supports_variadic_parameters(): void
    {
        $instance = TestDouble::for(VariadicInterface::class);

        $instance->allows('combine')->returns('a-b-c');

        $this->assertSame('a-b-c', $instance->combine('-', 'a', 'b', 'c'));
    }

    public function test_preserves_enum_case_default_values(): void
    {
        $generated = (new ClassGenerator)->generate(EnumDefaultInterface::class);

        $parameter = (new \ReflectionMethod($generated, 'draw'))->getParameters()[0];

        $this->assertTrue($parameter->isDefaultValueAvailable());
        $this->assertSame(Suit::Hearts, $parameter->getDefaultValue());
    }

    public function test_generated_double_satisfies_type_hints_in_real_collaborators(): void
    {
        $instance = TestDouble::for(BookRepositoryInterface::class);

        $instance->allows('find')->returns(new Book('Some Title'));

        $consumer = new class($instance)
        {
            public function __construct(public readonly BookRepositoryInterface $repository) {}
        };

        $this->assertInstanceOf(BookRepositoryInterface::class, $consumer->repository);
        $this->assertSame('Some Title', $consumer->repository->find(1)?->title);
    }
}
