<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use PHPUnit\Framework\TestCase;
use JMac\Testing\Engine\DoubleState;
use JMac\Testing\Engine\MethodExpectation;
use JMac\Testing\Engine\Mode;
use JMac\Testing\Exceptions\ModeConfigurationException;
use JMac\Testing\Tests\Fixtures\BookRepositoryInterface;

final class DoubleStateTest extends TestCase
{
    public function testTargetAndLabelAreExposed(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');

        $this->assertSame(BookRepositoryInterface::class, $state->target());
        $this->assertSame('BookRepositoryInterface', $state->label());
    }

    public function testModeDefaultsToStrictWhenUnset(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');

        $this->assertSame(Mode::Strict, $state->mode());
    }

    public function testSetModeCanOnlyHappenOnce(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');
        $state->setMode(Mode::Strict);

        $this->expectException(ModeConfigurationException::class);

        $state->setMode(Mode::Strict);
    }

    public function testExpectationsForFiltersByMethodNameAndPreservesRegistrationOrder(): void
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

    public function testRecordCallAndCallsForTrackEveryCallRegardlessOfMatching(): void
    {
        $state = new DoubleState(BookRepositoryInterface::class, 'BookRepositoryInterface');

        $state->recordCall('find', [1]);
        $state->recordCall('save', [new \stdClass()]);
        $state->recordCall('find', [2]);

        $this->assertSame([[1], [2]], $state->callsFor('find'));
    }

    public function testUnmetExpectationsExcludesSatisfiedOnes(): void
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
