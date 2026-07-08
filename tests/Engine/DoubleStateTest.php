<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Engine\Mode;
use JMac\Testing\Exceptions\ModeConfigurationException;
use JMac\Testing\Tests\Fixtures\BookRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class DoubleStateTest extends TestCase
{
    public function test_target_and_label_are_exposed(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');

        $this->assertSame(BookRepositoryInterface::class, $state->target());
        $this->assertSame('BookRepositoryInterface', $state->label());
    }

    public function test_mode_defaults_to_strict_when_unset(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');

        $this->assertSame(Mode::Strict, $state->mode());
    }

    public function test_set_mode_can_only_happen_once(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');
        $state->setMode(Mode::Strict);

        $this->expectException(ModeConfigurationException::class);

        $state->setMode(Mode::Strict);
    }

    public function test_expectations_for_filters_by_method_name_and_preserves_registration_order(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');
        $find = new MethodExpectation('find', required: false);
        $save = new MethodExpectation('save', required: false);
        $anotherFind = new MethodExpectation('find', required: false);

        $state->registerExpectation($find);
        $state->registerExpectation($save);
        $state->registerExpectation($anotherFind);

        $this->assertSame([$find, $anotherFind], $state->expectationsFor('find'));
        $this->assertSame([$save], $state->expectationsFor('save'));
    }

    public function test_record_call_and_calls_for_track_every_call_regardless_of_matching(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');

        $state->recordCall('find', [1]);
        $state->recordCall('save', [new \stdClass]);
        $state->recordCall('find', [2]);

        $this->assertSame([[1], [2]], $state->callsFor('find'));
    }

    public function test_unmet_expectations_excludes_satisfied_ones(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');
        $satisfied = new MethodExpectation('save', required: true);
        $unmet = new MethodExpectation('delete', required: true);

        $satisfied->recordMatch();

        $state->registerExpectation($satisfied);
        $state->registerExpectation($unmet);

        $this->assertSame([$unmet], $state->unmetExpectations());
    }
}
