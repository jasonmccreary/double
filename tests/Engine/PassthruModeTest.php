<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Double;
use JMac\Testing\Exceptions\PassthruAutoInstantiationException;
use JMac\Testing\Tests\Support\BookRepositoryInterface;
use JMac\Testing\Tests\Support\ConcreteLogger;
use JMac\Testing\Tests\Support\InstantiableLogger;
use JMac\Testing\Tests\Support\LoggerInterface;
use JMac\Testing\Tests\Support\RealLogger;
use PHPUnit\Framework\TestCase;

final class PassthruModeTest extends TestCase
{
    public function test_passthru_with_an_instance_delegates_unmatched_calls(): void
    {
        $real = new InstantiableLogger;
        $double = Double::for(InstantiableLogger::class)->passthru($real);

        $this->assertTrue($double->log('hello'));
    }

    public function test_passthru_still_intercepts_configured_calls(): void
    {
        $real = new InstantiableLogger;
        $double = Double::for(InstantiableLogger::class)->passthru($real);
        $double->allows('log')->returns(false);

        $this->assertFalse($double->log('hello'));
    }

    public function test_passthru_delegated_calls_are_still_recorded_for_spy_assertions(): void
    {
        $real = new InstantiableLogger;
        $double = Double::for(InstantiableLogger::class)->passthru($real);

        $double->log('hello');

        $this->assertSame([['hello']], Double::stateFor($double)->callsFor('log'));
    }

    public function test_passthru_with_no_argument_auto_instantiates_a_real_instance(): void
    {
        $double = Double::for(InstantiableLogger::class)->passthru();

        $this->assertTrue($double->log('hello'));
    }

    public function test_passthru_with_no_argument_on_an_interface_target_fails_clearly(): void
    {
        $double = Double::for(BookRepositoryInterface::class);

        $this->expectException(PassthruAutoInstantiationException::class);
        $this->expectExceptionMessage('->passthru($existingInstance)');

        $double->passthru();
    }

    public function test_passthru_with_no_argument_surfaces_a_throwing_constructor_clearly(): void
    {
        $double = Double::for(ConcreteLogger::class);

        $this->expectException(PassthruAutoInstantiationException::class);
        $this->expectExceptionMessage('->passthru($existingInstance)');

        $double->passthru();
    }

    public function test_for_with_a_real_instance_derives_the_double_from_its_class(): void
    {
        $real = new InstantiableLogger;
        $double = Double::for($real);

        $this->assertInstanceOf(InstantiableLogger::class, $double);
    }

    public function test_for_with_a_real_instance_does_not_change_the_default_mode(): void
    {
        $real = new InstantiableLogger;
        $double = Double::for($real);

        // Loose mode's safe default for a bool return is false — if for()
        // had silently switched to Passthru mode, this would delegate to
        // $real->log() instead and return true.
        $this->assertFalse($double->log('hello'));
    }

    public function test_for_with_a_real_instance_is_used_by_a_later_passthru_with_no_argument(): void
    {
        $real = new InstantiableLogger;
        $double = Double::for($real)->passthru();

        $this->assertSame($real, Double::stateFor($double)->passthruTarget());
        $this->assertTrue($double->log('hello'));
    }

    public function test_passthru_with_an_explicit_instance_overrides_the_one_remembered_from_for(): void
    {
        $remembered = new InstantiableLogger;
        $explicit = new InstantiableLogger;
        $double = Double::for($remembered)->passthru($explicit);

        $this->assertSame($explicit, Double::stateFor($double)->passthruTarget());
    }

    public function test_for_with_a_real_instance_still_satisfies_an_interface_the_class_implements(): void
    {
        $real = new RealLogger;
        $double = Double::for($real)->passthru();

        // PHP's own transitive interface inheritance through extends — not
        // anything this library does specially — so the double can still be
        // swapped into an IoC container wherever LoggerInterface is bound.
        $this->assertInstanceOf(LoggerInterface::class, $double);
        $this->assertTrue($double->log('hello'));
    }

    public function test_for_rejects_a_real_instance_mixed_into_a_multi_target_call(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Double::for(new InstantiableLogger, BookRepositoryInterface::class);
    }
}
