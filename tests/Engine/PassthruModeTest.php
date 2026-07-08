<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\TestDouble;
use JMac\Testing\Exceptions\PassthruAutoInstantiationException;
use JMac\Testing\Tests\Fixtures\BookRepositoryInterface;
use JMac\Testing\Tests\Fixtures\ConcreteLogger;
use JMac\Testing\Tests\Fixtures\InstantiableLogger;
use PHPUnit\Framework\TestCase;

final class PassthruModeTest extends TestCase
{
    public function test_passthru_with_an_instance_delegates_unmatched_calls(): void
    {
        $real = new InstantiableLogger;
        $double = TestDouble::for(InstantiableLogger::class)->passthru($real);

        $this->assertTrue($double->log('hello'));
    }

    public function test_passthru_still_intercepts_configured_calls(): void
    {
        $real = new InstantiableLogger;
        $double = TestDouble::for(InstantiableLogger::class)->passthru($real);
        $double->allows('log')->returns(false);

        $this->assertFalse($double->log('hello'));
    }

    public function test_passthru_delegated_calls_are_still_recorded_for_spy_assertions(): void
    {
        $real = new InstantiableLogger;
        $double = TestDouble::for(InstantiableLogger::class)->passthru($real);

        $double->log('hello');

        $this->assertSame([['hello']], TestDouble::stateFor($double)->callsFor('log'));
    }

    public function test_passthru_with_no_argument_auto_instantiates_a_real_instance(): void
    {
        $double = TestDouble::for(InstantiableLogger::class)->passthru();

        $this->assertTrue($double->log('hello'));
    }

    public function test_passthru_with_no_argument_on_an_interface_target_fails_clearly(): void
    {
        $double = TestDouble::for(BookRepositoryInterface::class);

        $this->expectException(PassthruAutoInstantiationException::class);
        $this->expectExceptionMessage('->passthru($existingInstance)');

        $double->passthru();
    }

    public function test_passthru_with_no_argument_surfaces_a_throwing_constructor_clearly(): void
    {
        $double = TestDouble::for(ConcreteLogger::class);

        $this->expectException(PassthruAutoInstantiationException::class);
        $this->expectExceptionMessage('->passthru($existingInstance)');

        $double->passthru();
    }
}
