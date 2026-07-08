<?php

declare(strict_types=1);

namespace TestDouble\Tests\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TestDouble\Engine\ClassGenerator;
use TestDouble\Engine\TestDouble;
use TestDouble\Exceptions\InvalidDoubleTargetException;
use TestDouble\Exceptions\ReservedNameCollisionException;
use TestDouble\Tests\Fixtures\AllowsCollisionInterface;
use TestDouble\Tests\Fixtures\AuthorizerInterface;
use TestDouble\Tests\Fixtures\Book;
use TestDouble\Tests\Fixtures\BookRepositoryInterface;
use TestDouble\Tests\Fixtures\ConcreteLogger;
use TestDouble\Tests\Fixtures\EnumDefaultInterface;
use TestDouble\Tests\Fixtures\ExpectsCollisionInterface;
use TestDouble\Tests\Fixtures\FinalLogger;
use TestDouble\Tests\Fixtures\NullableParamInterface;
use TestDouble\Tests\Fixtures\PassthruCollisionInterface;
use TestDouble\Tests\Fixtures\ReceivedCollisionInterface;
use TestDouble\Tests\Fixtures\StrictCollisionInterface;
use TestDouble\Tests\Fixtures\Suit;
use TestDouble\Tests\Fixtures\UnionTypeInterface;
use TestDouble\Tests\Fixtures\VariadicInterface;

final class ClassGeneratorTest extends TestCase
{
    public function testGeneratesAClassImplementingATargetInterface(): void
    {
        $generated = (new ClassGenerator())->generate(BookRepositoryInterface::class);

        $this->assertTrue(class_exists($generated));
        $this->assertTrue(is_subclass_of($generated, BookRepositoryInterface::class));
    }

    public function testGeneratesAClassExtendingATargetClassWithoutInvokingItsConstructor(): void
    {
        $generated = (new ClassGenerator())->generate(ConcreteLogger::class);

        $this->assertTrue(is_subclass_of($generated, ConcreteLogger::class));

        // Instantiating via the real (still-inherited) constructor would throw;
        // the generated class's own factory bypasses it entirely.
        $instance = $generated::__td_instantiate();

        $this->assertInstanceOf(ConcreteLogger::class, $instance);
    }

    public function testEachCallToGenerateProducesADistinctClassForTheSameTarget(): void
    {
        $generator = new ClassGenerator();

        $first = $generator->generate(BookRepositoryInterface::class);
        $second = $generator->generate(BookRepositoryInterface::class);

        $this->assertNotSame($first, $second);
        $this->assertTrue(class_exists($first));
        $this->assertTrue(class_exists($second));
    }

    public function testRejectsANonExistentTarget(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage('no such class or interface exists');

        (new ClassGenerator())->generate('TestDouble\Tests\Fixtures\NoSuchThing');
    }

    public function testRejectsAFinalClass(): void
    {
        $this->expectException(InvalidDoubleTargetException::class);
        $this->expectExceptionMessage('declared final');

        (new ClassGenerator())->generate(FinalLogger::class);
    }

    public static function reservedNameFixtures(): iterable
    {
        yield 'expects' => [ExpectsCollisionInterface::class, 'expects'];
        yield 'allows' => [AllowsCollisionInterface::class, 'allows'];
        yield 'strict' => [StrictCollisionInterface::class, 'strict'];
        yield 'passthru' => [PassthruCollisionInterface::class, 'passthru'];
        yield 'received' => [ReceivedCollisionInterface::class, 'received'];
        yield 'AuthorizerInterface allows()' => [AuthorizerInterface::class, 'allows'];
    }

    #[DataProvider('reservedNameFixtures')]
    public function testRejectsATargetDeclaringAReservedControlMethodName(string $target, string $method): void
    {
        try {
            (new ClassGenerator())->generate($target);
            $this->fail('Expected ReservedNameCollisionException to be thrown.');
        } catch (ReservedNameCollisionException $exception) {
            $this->assertStringContainsString($target, $exception->getMessage());
            $this->assertStringContainsString($method, $exception->getMessage());
        }
    }

    public function testNeverEmitsAnImplicitNullableParameterSignature(): void
    {
        $generated = (new ClassGenerator())->generate(NullableParamInterface::class);

        $parameter = (new \ReflectionMethod($generated, 'greet'))->getParameters()[0];

        $this->assertTrue($parameter->allowsNull());
        $this->assertSame('?string', (string) $parameter->getType());
    }

    public function testGeneratedMethodsWithExplicitNullableParametersTriggerNoDeprecation(): void
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

    public function testDoesNotAddASpuriousNullableMarkerToAGenuinelyUntypedParameter(): void
    {
        $generated = (new ClassGenerator())->generate(NullableParamInterface::class);

        $parameter = (new \ReflectionMethod($generated, 'untypedDefaultNull'))->getParameters()[0];

        $this->assertNull($parameter->getType());
        $this->assertTrue($parameter->isDefaultValueAvailable());
        $this->assertNull($parameter->getDefaultValue());
    }

    public function testSupportsUnionTypedParametersAndReturns(): void
    {
        $instance = TestDouble::for(UnionTypeInterface::class);

        $instance->allows('accept')->returns('ok');

        $this->assertSame('ok', $instance->accept(123));
    }

    public function testSupportsVariadicParameters(): void
    {
        $instance = TestDouble::for(VariadicInterface::class);

        $instance->allows('combine')->returns('a-b-c');

        $this->assertSame('a-b-c', $instance->combine('-', 'a', 'b', 'c'));
    }

    public function testPreservesEnumCaseDefaultValues(): void
    {
        $generated = (new ClassGenerator())->generate(EnumDefaultInterface::class);

        $parameter = (new \ReflectionMethod($generated, 'draw'))->getParameters()[0];

        $this->assertTrue($parameter->isDefaultValueAvailable());
        $this->assertSame(Suit::Hearts, $parameter->getDefaultValue());
    }

    public function testGeneratedDoubleSatisfiesTypeHintsInRealCollaborators(): void
    {
        $instance = TestDouble::for(BookRepositoryInterface::class);

        $instance->allows('find')->returns(new Book('Some Title'));

        $consumer = new class ($instance) {
            public function __construct(public readonly BookRepositoryInterface $repository)
            {
            }
        };

        $this->assertInstanceOf(BookRepositoryInterface::class, $consumer->repository);
        $this->assertSame('Some Title', $consumer->repository->find(1)?->title);
    }
}
