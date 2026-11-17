<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Support;

/**
 * Mirrors Illuminate\Console\OutputStyle::writeln(string $messages, int $type
 * = self::OUTPUT_NORMAL): a base class declaring parameters defaulted to a
 * "self"/"parent"-qualified class constant, inherited unoverridden by a
 * further subclass — the shape that must be doubled without a fatal
 * "'\self' is an invalid class name" error.
 * The trait's __CLASS__ default must also keep this declaring-class context.
 */
class ConstDefaultBase extends ConstDefaultGrandparent
{
    use ClassNameDefaultTrait;

    public const SELF_MODE = 1;

    public function withSelfConstant(int $mode = self::SELF_MODE): int
    {
        return $mode;
    }

    public function withParentConstant(int $mode = parent::PARENT_MODE): int
    {
        return $mode;
    }
}
